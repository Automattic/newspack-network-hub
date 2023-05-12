<?php
/**
 * Newspack Hub Event Log Store
 *
 * @package Newspack
 */

namespace Newspack_Hub\Stores;

use Newspack_Hub\Accepted_Actions;
use Newspack_Hub\Debugger;
use Newspack_Hub\Node;
use Newspack_Hub\Incoming_Events\Abstract_Incoming_Event;
use Newspack_Hub\Database\Event_Log as Database;

/**
 * Class to handle Event Log Store
 */
class Event_Log {

	/**
	 * Get event log items
	 *
	 * @param array $args See {@see self::build_where_clause()} for supported arguments.
	 * @param int   $per_page Number of items to return per page.
	 * @param int   $page Page number to return.
	 * @return Abstract_Event_Log_Item[]
	 */
	public static function get( $args, $per_page = 10, $page = 1 ) {
		global $wpdb;

		$offset = ( $page - 1 ) * $per_page;

		$table_name = Database::get_table_name();

		$query = $wpdb->prepare( "SELECT * FROM $table_name WHERE 1=1 [args] ORDER BY ID DESC LIMIT %d OFFSET %d", $per_page, $offset ); //phpcs:ignore

		$query = str_replace( '[args]', self::build_where_clause( $args ), $query );

		$db = $wpdb->get_results( $query ); //phpcs:ignore

		$results = [];

		foreach ( $db as $item ) {
			if ( empty( Accepted_Actions::ACTIONS[ $item->action_name ] ) ) {
				continue;
			}
			$class_name = 'Newspack_Hub\\Stores\\Event_Log_Items\\' . Accepted_Actions::ACTIONS[ $item->action_name ];
			$results[]  = new $class_name(
				[
					'id'          => $item->id,
					'node'        => new Node( $item->node_id ),
					'action_name' => $item->action_name,
					'email'       => $item->email,
					'data'        => $item->data,
					'timestamp'   => $item->timestamp,
				]
			);
		}

		return $results;
	}

	/**
	 * Get the total number of items for a query
	 *
	 * @param array $args See {@see self::build_where_clause()} for supported arguments.
	 * @return int
	 */
	public static function get_total_items( $args ) { 
		global $wpdb;
		$table_name = Database::get_table_name();
		$query      = "SELECT COUNT(*) FROM $table_name WHERE 1=1 [args]";
		$query      = str_replace( '[args]', self::build_where_clause( $args ), $query );
		$result     = $wpdb->get_var( $query ); //phpcs:ignore
		return $result; 
	}

	/**
	 * Build the WHERE clause for the query
	 *
	 * @param array $args {
	 *      The query arguments. Supported arguments are below.
	 * 
	 *      @type string $search Search string to search for in the event log. It will search in the email, action_name and data fields.
	 *      @type int $node_id The ID of the node to filter by.
	 *      @type string $action_name The name of the action to filter by.
	 * }
	 * @return string The WHERE clause for the query.
	 */
	protected static function build_where_clause( $args ) {
		global $wpdb;
		$where = '';

		if ( ! empty( $args['node_id'] ) ) {
			$where .= $wpdb->prepare( ' AND node_id = %d', $args['node_id'] );
		}

		if ( ! empty( $args['action_name'] ) ) {
			$where .= $wpdb->prepare( ' AND action_name = %s', $args['action_name'] );
		}

		if ( ! empty( $args['search'] ) ) {
			$where .= $wpdb->prepare( ' AND ( email LIKE %s ', '%' . $args['search'] . '%' );
			$where .= $wpdb->prepare( ' OR action_name LIKE %s ', '%' . $args['search'] . '%' );
			$where .= $wpdb->prepare( ' OR data LIKE %s ', '%' . $args['search'] . '%' );
			$where .= ')';
		}
		return $where;
	}
	
	/**
	 * Persists an event to the database
	 *
	 * @param Abstract_Incoming_Event $event The Incoming Event to be persisted.
	 * @return int|false The ID of the inserted row, or false on failure.
	 */
	public static function persist( Abstract_Incoming_Event $event ) {
		global $wpdb;
		Debugger::log( 'Persisting Event' );
		$insert = $wpdb->insert( //phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			Database::get_table_name(),
			[
				'node_id'     => $event->get_node_id(),
				'action_name' => $event->get_action_name(),
				'email'       => $event->get_email(),
				'data'        => wp_json_encode( $event->get_data() ),
				'timestamp'   => $event->get_timestamp(),
			]
		);
		Debugger::log( $insert );
		if ( ! $insert ) {
			return false;
		}
		return $wpdb->insert_id;
	}
}
