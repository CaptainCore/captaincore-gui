<?php

function mailgun_setup( $domain ) {

	// Prep to handle remote responses
	$responses = '';

	// Load Mailgun API client
	include_once ABSPATH . '/vendor/autoload.php';
	$mgClient = new \Mailgun\Mailgun( MAILGUN_API_KEY );

	// Prep Mailgun domain variable
	$mailgun_subdomain = "mg.$domain";

	// Fetch all domains from Mailgun
	$results = $mgClient->get( 'domains' );
	foreach ( $results->http_response_body->items as $result ) {
		if ( $result->name == $mailgun_subdomain ) {
			$mailgun_domain_found = $mailgun_subdomain;
			if ( $result->state == 'unverified' ) {
				$mailgun_domain_unverified = true;
			}
		}
	}

	// If Mailgun domain already exists then exit
	if ( $mailgun_domain_found && ! $mailgun_domain_unverified ) {
		return "Mailgun domain $mailgun_domain_found already entered and verified";
	}

	if ( $mailgun_domain_found ) {

		// Fetch domain from Mailgun
		$result = $mgClient->get( "domains/$mailgun_subdomain" );

	} else {

		// Create domain in Mailgun
		$result = $mgClient->post(
			'domains', array(
				'name' => $mailgun_subdomain,
			)
		);

	}

	$mailgun_receiving_dns_records = $result->http_response_body->receiving_dns_records;
	$mailgun_sending_dns_records   = $result->http_response_body->sending_dns_records;

	// Load Constellix domains from transient
	$constellix_all_domains = get_transient( 'constellix_all_domains' );

	// If empty then update transient with large remote call
	if ( empty( $constellix_all_domains ) ) {

		// Fetch Constellix domains
		$constellix_all_domains = constellix_api_get( 'domains' );

		// Save the API response so we don't have to call again until tomorrow.
		set_transient( 'constellix_all_domains', $constellix_all_domains, HOUR_IN_SECONDS );

	}

	// Check Consellix for domain
	foreach ( $constellix_all_domains as $constellix_domain ) {

		// Search API for domain ID
		if ( $domain == $constellix_domain->name ) {
			$domain_id = $constellix_domain->id;
		}
	}

	// Found domain ID from Consellix so add Mailgun dns records
	if ( $domain_id ) {

		// Loop through Mailgun's API new receiving records and prep for Constellix
		$mx_records = [];
		foreach ( $mailgun_receiving_dns_records as $record ) {
			if ( $record->record_type == 'MX' and $record->valid != 'valid' ) {
				$mx_records[] = array(
					'value'       => $record->value . '.',
					'level'       => $record->priority,
					'disableFlag' => false,
				);
			}
		}

		// Prep new Constellix records
		$record_type = 'mx';
		$post        = array(
			'recordOption' => 'roundRobin',
			'name'         => 'mg',
			'ttl'          => '3600',
			'roundRobin'   => $mx_records,
		);

		// Post to new MX records to Constellix
		$response = constellix_api_post( "domains/$domain_id/records/$record_type", $post );

		// Capture responses
		foreach ( $response as $result ) {
			if ( is_array( $result ) ) {
				$result['errors'] = $result[0];
				$responses        = $responses . json_encode( $result ) . ',';
			} else {
				$responses = $responses . json_encode( $result ) . ',';
			}
		}

		// Loop through Mailgun's API new receiving records and prep for Constellix
		foreach ( $mailgun_sending_dns_records as $record ) {
			if ( $record->record_type == 'TXT' and $record->valid != 'valid' ) {
				$record_name_without_domain = str_replace( '.' . $domain, '', $record->name );
				$post                       = array(
					'recordOption' => 'roundRobin',
					'name'         => $record_name_without_domain,
					'ttl'          => '3600',
					'roundRobin'   => array(
						array(
							'value'       => $record->value,
							'disableFlag' => false,
						),
					),
				);

				$response = constellix_api_post( "domains/$domain_id/records/txt", $post );
				foreach ( $response as $result ) {
					if ( is_array( $result ) ) {
						$result['errors'] = $result[0];
						$responses        = $responses . json_encode( $result ) . ',';
					} else {
						$responses = $responses . json_encode( $result ) . ',';
					}
				}
			}
			if ( $record->record_type == 'CNAME' and $record->valid != 'valid' ) {

				$record_name_without_domain = str_replace( '.' . $domain, '', $record->name );

				$post = array(
					'name' => $record_name_without_domain,
					'host' => "$record->value.",
					'ttl'  => 3600,
				);

				$response = constellix_api_post( "domains/$domain_id/records/cname", $post );

				foreach ( $response as $result ) {
					if ( is_array( $result ) ) {
						$result['errors'] = $result[0];
						$responses        = $responses . json_encode( $result ) . ',';
					} else {
						$responses = $responses . json_encode( $result ) . ',';
					}
				}
			}
		}
	}

	// Valid Mailgun domains
	$result = $mgClient->put(
		"domains/$mailgun_subdomain/verify", array(
			'domain' => "$mailgun_subdomain",
		)
	);

	// Update website detail with new Mailgun subdomain
	// Finding matching site by domain name (title)
	$args  = array(
		'post_type' => 'captcore_website',
		'title'     => $domain,
	);
	$sites = get_posts( $args );

	// Assign site id
	if ( count( $sites ) == 1 ) {
		// Assign ID
		$site_id = $sites[0]->ID;
		// Updates domain details
		update_field( 'field_5a985788d43a6', $mailgun_subdomain, $site_id );
	}

	// In 1 minute run Mailgun verify domain
	wp_schedule_single_event( time() + 60, 'schedule_mailgun_verify', array( $domain ) );

	if ( $responses ) {
		return $responses;
	}

}

// Hook to run mailgun_verify() at a later time
add_action( 'schedule_mailgun_verify', 'mailgun_verify', 10, 3 );

function mailgun_verify( $domain ) {

	// Load Mailgun API client
	include_once ABSPATH . '/vendor/autoload.php';
	$mgClient = new \Mailgun\Mailgun( MAILGUN_API_KEY );

	// Prep Mailgun domain variable
	$mailgun_subdomain = "mg.$domain";

	// Valid Mailgun domains
	$result = $mgClient->put(
		"domains/$mailgun_subdomain/verify", array(
			'domain' => "$mailgun_subdomain",
		)
	);

	// Check if records are valid. If not need to flag the domain
	// (TO DO: add place to flag domain with automattic retry schedule. 60sec, 3 minutes, 6 minutes, 1hr, 24hrs)
	$mailgun_receiving_dns_records = $result->http_response_body->receiving_dns_records;
	$mailgun_sending_dns_records   = $result->http_response_body->sending_dns_records;

	return $result->http_response_body->domain->state;

}

function mailgun_events( $mailgun_subdomain, $page = "" ) {

	// Prep to handle remote responses
	$response             = (object) [];
	$response->items      = [];
	$response->pagination = [];

	// Load Mailgun API client
	$mailgun = \Mailgun\Mailgun::create( MAILGUN_API_KEY );

	$queryString = [
		'event' => 'accepted OR rejected OR delivered OR failed OR complained',
		'limit' => 300,
	];

	if ( $page != "" ) {
		// Fetch all domains from Mailgun
		$results = wp_remote_get( $page, [
			"headers" => [ "Authorization" => "Basic " . base64_encode ( "api:" . MAILGUN_API_KEY ) ],
		] );
		$results = json_decode( $results['body'] );
		foreach ( $results->items as $item ) {
			$description = $item->recipient;
			if ( $item->message->headers->from ) {
				$from        = $item->message->headers->from;
				$description = "{$from} -> {$description}";
			}
			$response->items[] = [
				"timestamp"   => $item->timestamp,
				"event"       => $item->event,
				"description" => $description,
				"message"     => $item->message,
			];
			$response->pagination["next"]     = $results->paging->next;
		}
		return $response;
	}

	$results = $mailgun->events()->get( "$mailgun_subdomain", $queryString );
	foreach ( $results->getItems() as $item ) {
		$description = $item->getRecipient();
		if ( $item->getMessage()["headers"]["from"] ) {
			$from        = $item->getMessage()["headers"]["from"];
			$description = "{$from} -> {$description}";
		}

		$response->items[] = [
			"timestamp"   => $item->getTimestamp(),
			"event"       => $item->getEvent(),
			"description" => $description,
			"message"     => $item->getMessage(),
		];
	}
	$response->pagination["next"]     = $results->getNextUrl();

	return $response;

}
