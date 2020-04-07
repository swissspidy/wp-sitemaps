<?php
/**
 * Sitemaps: Core_Sitemaps_Provider class
 *
 * This class is a base class for other sitemap providers to extend and contains shared functionality.
 *
 * @package WordPress
 * @subpackage Sitemaps
 * @since x.x.x
 */

/**
 * Class Core_Sitemaps_Provider
 */
class Core_Sitemaps_Provider {
	/**
	 * Post type name.
	 *
	 * @var string
	 */
	protected $object_type = '';

	/**
	 * Sub type name.
	 *
	 * @var string
	 */
	protected $sub_type = '';

	/**
	 * Set up relevant rewrite rules, actions, and filters.
	 */
	public function setup() {
		// Set up async tasks related to calculating lastmod data.
		add_action( 'core_sitemaps_calculate_lastmod', array( $this, 'calculate_sitemap_lastmod' ), 10, 3 );
		add_action( 'core_sitemaps_update_lastmod_' . $this->object_type, array( $this, 'update_lastmod_values' ) );

		if ( ! wp_next_scheduled( 'core_sitemaps_update_lastmod_' . $this->object_type ) && ! wp_installing() ) {

			/**
			 * Filter the recurrence value for updating sitemap lastmod values.
			 *
			 * @since 0.1.0
			 *
			 * @param string $recurrence How often the event should subsequently recur. Default 'twicedaily'.
			 *                           See wp_get_schedules() for accepted values.
			 * @param string $type       The object type being handled by this event, e.g. posts, taxonomies, users.
			 */
			$lastmod_recurrence = apply_filters( 'core_sitemaps_lastmod_recurrence', 'twicedaily', $this->object_type );

			wp_schedule_event( time(), $lastmod_recurrence, 'core_sitemaps_update_lastmod_' . $this->object_type );
		}
	}

	/**
	 * Return object type being queried.
	 *
	 * @return string Name of the object type.
	 */
	public function get_queried_type() {
		$type = $this->sub_type;

		if ( empty( $type ) ) {
			return $this->object_type;
		}

		return $type;
	}

	/**
	 * Query for determining the number of pages.
	 *
	 * @param string $type Optional. Object type. Default is null.
	 * @return int Total number of pages.
	 */
	public function max_num_pages( $type = '' ) {
		if ( empty( $type ) ) {
			$type = $this->get_queried_type();
		}

		$query = new WP_Query(
			array(
				'fields'                 => 'ids',
				'orderby'                => 'ID',
				'order'                  => 'ASC',
				'post_type'              => $type,
				'posts_per_page'         => core_sitemaps_get_max_urls( $this->object_type ),
				'paged'                  => 1,
				'update_post_term_cache' => false,
				'update_post_meta_cache' => false,
			)
		);

		return isset( $query->max_num_pages ) ? $query->max_num_pages : 1;
	}

	/**
	 * Set the object sub_type.
	 *
	 * @param string $sub_type The name of the object subtype.
	 * @return bool Returns true on success.
	 */
	public function set_sub_type( $sub_type ) {
		$this->sub_type = $sub_type;

		return true;
	}

	/**
	 * Get data about each sitemap type.
	 *
	 * @return array List of sitemap types including object subtype name and number of pages.
	 */
	public function get_sitemap_type_data() {
		$sitemap_data = array();

		$sitemap_types = $this->get_object_sub_types();

		foreach ( $sitemap_types as $type ) {
			// Handle lists of post-objects.
			if ( isset( $type->name ) ) {
				$type = $type->name;
			}

			$sitemap_data[] = array(
				'name'   => $type,
				'pages' => $this->max_num_pages( $type ),
			);
		}

		return $sitemap_data;
	}

	/**
	 * List of sitemap pages exposed by this provider.
	 *
	 * The returned data is used to populate the sitemap entries of the index.
	 *
	 * @return array List of sitemaps.
	 */
	public function get_sitemap_entries() {
		$sitemaps = array();

		$sitemap_types = $this->get_sitemap_type_data();

		foreach ( $sitemap_types as $type ) {
			for ( $page = 1; $page <= $type['pages']; $page ++ ) {
				$loc        = $this->get_sitemap_url( $type['name'], $page );
				$lastmod    = $this->get_sitemap_lastmod( $type['name'], $page );
				$sitemaps[] = array(
					'loc'     => $loc,
					'lastmod' => $lastmod,
				);
			}
		}

		return $sitemaps;
	}

	/**
	 * Get the URL of a sitemap entry.
	 *
	 * @param string $name The name of the sitemap.
	 * @param int    $page The page of the sitemap.
	 *
	 * @return string The composed URL for a sitemap entry.
	 */
	public function get_sitemap_url( $name, $page ) {
		/* @var WP_Rewrite $wp_rewrite */
		global $wp_rewrite;

		$basename = sprintf(
			'/wp-sitemap-%1$s.xml',
			// Accounts for cases where name is not included, ex: sitemaps-users-1.xml.
			implode( '-', array_filter( array( $this->object_type, $name, (string) $page ) ) )
		);

		$url = home_url( $basename );

		if ( ! $wp_rewrite->using_permalinks() ) {
			$url = add_query_arg(
				array(
					'sitemap'          => $this->object_type,
					'sitemap-sub-type' => $name,
					'paged'            => $page,
				),
				home_url( '/' )
			);
		}

		return $url;
	}

	/**
	 * Get the last modified date for a sitemap page.
	 *
	 * This will be overridden in provider subclasses.
	 *
	 * @param string $name The name of the sitemap.
	 * @param int    $page The page of the sitemap being returned.
	 * @return string The GMT date of the most recently changed date.
	 */
	public function get_sitemap_lastmod( $name, $page ) {
		$type = implode( '_', array_filter( array( $this->object_type, $name, (string) $page ) ) );

		// Check for an option.
		$lastmod = get_option( "core_sitemaps_lastmod_$type", '' );

		// If blank, schedule a job.
		if ( empty( $lastmod ) && ! wp_doing_cron() ) {
			$event_args = array( $this->object_type, $name, $page );

			// Don't schedule a duplicate job.
			if ( ! wp_next_scheduled( 'core_sitemaps_calculate_lastmod', $event_args ) ) {
				wp_schedule_single_event( time(), 'core_sitemaps_calculate_lastmod', $event_args );
			}
		}

		return $lastmod;
	}

	/**
	 * Calculate lastmod date for a sitemap page.
	 *
	 * Calculated value is saved to the database as an option.
	 *
	 * @param string $type    The object type of the page: posts, taxonomies, users, etc.
	 * @param string $subtype The object subtype if applicable, e.g., post type, taxonomy type.
	 * @param int    $page    The page number.
	 */
	public function calculate_sitemap_lastmod( $type, $subtype, $page ) {
		if ( $type !== $this->object_type ) {
			return;
		}

		// Get the list of URLs from this page and sort it by lastmod date.
		$url_list    = $this->get_url_list( $page, $subtype );
		$sorted_list = wp_list_sort( $url_list, 'lastmod', 'DESC' );

		// Use the most recent lastmod value as the lastmod value for the sitemap page.
		$lastmod = reset( $sorted_list )['lastmod'];

		$suffix = implode( '_', array_filter( array( $type, $subtype, (string) $page ) ) );

		update_option( "core_sitemaps_lastmod_$suffix", $lastmod );
	}

	/**
	 * Schedules asynchronous tasks to update lastmod entries for all sitemap pages.
	 */
	public function update_lastmod_values() {
		$sitemap_types = $this->get_sitemap_type_data();

		foreach ( $sitemap_types as $type ) {
			for ( $page = 1; $page <= $type['pages']; $page ++ ) {
				wp_schedule_single_event( time(), 'core_sitemaps_calculate_lastmod', array( $this->object_type, $this->sub_type, $page ) );
			}
		}
	}

	/**
	 * Return the list of supported object sub-types exposed by the provider.
	 *
	 * By default this is the sub_type as specified in the class property.
	 *
	 * @return array List: containing object types or false if there are no subtypes.
	 */
	public function get_object_sub_types() {
		if ( ! empty( $this->sub_type ) ) {
			return array( $this->sub_type );
		}

		/**
		 * To prevent complexity in code calling this function, such as `get_sitemaps()` in this class,
		 * an iterable type is returned. The value false was chosen as it passes empty() checks and
		 * as semantically this provider does not provide sub-types.
		 *
		 * @link https://github.com/GoogleChromeLabs/wp-sitemaps/pull/72#discussion_r347496750
		 */
		return array( false );
	}
}
