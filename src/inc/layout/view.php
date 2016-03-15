<?php
/**
 * @package Make
 */

/**
 * Class MAKE_Layout_View
 *
 * @since x.x.x.
 */
final class MAKE_Layout_View extends MAKE_Util_Modules implements MAKE_Layout_ViewInterface, MAKE_Util_LoadInterface {
	/**
	 * An associative array of required modules.
	 *
	 * @since x.x.x.
	 *
	 * @var array
	 */
	protected $dependencies = array(
		'error'         => 'MAKE_Error_CollectorInterface',
		'compatibility' => 'MAKE_Compatibility_MethodsInterface',
	);

	/**
	 * @var array
	 */
	private $views = array();

	/**
	 * The default view.
	 *
	 * @since x.x.x.
	 *
	 * @var string
	 */
	private $default_view = 'post';

	/**
	 * Indicator of whether the load routine has been run.
	 *
	 * @since x.x.x.
	 *
	 * @var bool
	 */
	private $loaded = false;

	/**
	 * Load data files.
	 *
	 * @since x.x.x.
	 *
	 * @return void
	 */
	public function load() {
		if ( true === $this->is_loaded() ) {
			return;
		}

		$views = array(
			'blog' => array(
				'label'    => __( 'Blog (Post Page)', 'make' ),
				'callback' => 'is_home',
			),
			'archive' => array(
				'label'    => __( 'Archives', 'make' ),
				'callback' => 'is_archive',
			),
			'search' => array(
				'label'    => __( 'Search Results', 'make' ),
				'callback' => 'is_search',
			),
			'page' => array(
				'label'    => __( 'Pages', 'make' ),
				'callback' => array( $this, 'callback_page' ),
			),
			'post' => array(
				'label'    => __( 'Posts', 'make' ),
				'callback' => array( $this, 'callback_post' ),
			),
		);

		foreach ( $views as $view_id => $view_args ) {
			$this->add_view( $view_id, $view_args );
		}

		// Loading has occurred.
		$this->loaded = true;

		/**
		 * Action: Fires at the end of the view object's load method.
		 *
		 * This action gives a developer the opportunity to add or modify views
		 * and run additional load routines.
		 *
		 * @since x.x.x.
		 *
		 * @param MAKE_Layout_View    $view    The view object that has just finished loading.
		 */
		do_action( 'make_view_loaded', $this );
	}

	/**
	 * Check if the load routine has been run.
	 *
	 * @since x.x.x.
	 *
	 * @return bool
	 */
	public function is_loaded() {
		return $this->loaded;
	}

	/**
	 * Add or update a view definition.
	 *
	 * Example:
	 * add_view(
	 *     'page',
	 *     array(
	 *         'label'    => __( 'Page', 'make' ),
	 *         'callback' => 'is_page',
	 *         'priority' => 10
	 *     ),
	 *     true
	 * );
	 *
	 * @since x.x.x.
	 *
	 * @param string $view_id
	 * @param array  $args
	 * @param bool   $overwrite
	 *
	 * @return bool
	 */
	public function add_view( $view_id, array $args = array(), $overwrite = false ) {
		// Make sure we're not doing it wrong.
		if ( 'make_view_loaded' !== current_action() && did_action( 'make_view_loaded' ) ) {
			$this->compatibility()->doing_it_wrong(
				__FUNCTION__,
				__( 'This function should only be called during or before the <code>make_view_loaded</code> action.', 'make' ),
				'1.7.0'
			);

			return false;
		}

		$view_id = sanitize_key( $view_id );
		$new_view_args = array();
		$return = false;

		// Overwrite an existing view.
		if ( isset( $this->views[ $view_id ] ) && true === $overwrite ) {
			$new_view_args = wp_parse_args( $args, $this->views[ $view_id ] );
		}
		// View already exists, overwriting disabled.
		else if ( isset( $this->views[ $view_id ] ) && true !== $overwrite ) {
			$this->error()->add_error(
				'make_view_already_exists',
				sprintf(
					__( 'The "%s" view can\'t be added because it already exists.', 'make' ),
					esc_html( $view_id )
				)
			);
		}
		// Add a new view.
		else {
			// Merge defaults
			$defaults = array(
				'label'    => ucwords( preg_replace( '/[\-_]*/', ' ', $view_id ) ),
				'callback' => '',
				'priority' => 10,
			);
			$new_view_args = wp_parse_args( $args, $defaults );
		}

		if ( ! empty( $new_view_args ) ) {
			// Validate the callback.
			if ( is_callable( $new_view_args['callback'] ) ) {
				$this->views[ $view_id ] = $new_view_args;
				$return                  = true;
			} else {
				$this->error()->add_error(
					'make_view_callback_not_valid',
					sprintf(
						__( 'The view callback (%1$s) for "%2$s" is not valid.', 'make' ),
						esc_html( print_r( $args['callback'], true ) ),
						esc_html( $view_id )
					)
				);
			}
		}

		return $return;
	}

	/**
	 * Remove a view definition, if it exists.
	 *
	 * @since x.x.x.
	 *
	 * @param string $view_id
	 *
	 * @return bool
	 */
	public function remove_view( $view_id ) {
		if ( ! isset( $this->views[ $view_id ] ) ) {
			$this->error()->add_error( 'make_view_cannot_remove', sprintf( __( 'The "%s" view can\'t be removed because it doesn\'t exist.', 'make' ), esc_html( $view_id ) ) );
			return false;
		} else {
			unset( $this->views[ $view_id ] );
		}

		return true;
	}

	/**
	 * Get an array of complete view definitions, or a specific property of each one.
	 *
	 * If the view definition doesn't have the specified property, it will be omitted.
	 *
	 * @param string $property
	 *
	 * @return array
	 */
	public function get_views( $property = 'all' ) {
		if ( ! $this->is_loaded() ) {
			$this->load();
		}

		if ( 'all' === $property ) {
			return $this->views;
		}

		$views = array();

		foreach ( $this->views as $view_id => $properties ) {
			if ( isset( $properties[ $property ] ) ) {
				$views[ $view_id ] = $properties[ $property ];
			}
		}

		return $views;
	}

	/**
	 * Get a sorted array of view definitions, based on the priority property.
	 *
	 * @since x.x.x.
	 *
	 * @return array
	 */
	public function get_sorted_views() {
		$views = $this->get_views();
		$prioritizer = array();

		foreach ( $views as $view_id => $view_args ) {
			$priority = absint( $view_args['priority'] );

			if ( ! isset( $prioritizer[ $priority ] ) ) {
				$prioritizer[ $priority ] = array();
			}

			$prioritizer[ $priority ][ $view_id ] = $view_args;
		}

		ksort( $prioritizer );

		$sorted_views = array();

		foreach ( $prioritizer as $view_group ) {
			$sorted_views = array_merge( $sorted_views, $view_group );
		}

		return $sorted_views;
	}

	/**
	 * Check if a particular view exists.
	 *
	 * @since x.x.x.
	 *
	 * @param string $view_id
	 *
	 * @return bool
	 */
	public function view_exists( $view_id ) {
		$views = $this->get_views();
		return isset( $views[ $view_id ] );
	}

	/**
	 * Get the label for a particular view, if it exists.
	 *
	 * @since x.x.x.
	 *
	 * @param string $view_id
	 *
	 * @return string
	 */
	public function get_view_label( $view_id ) {
		$label = '';

		if ( $this->view_exists( $view_id ) ) {
			$views = $this->get_views();
			$label = ( isset( $views[ $view_id ]['label'] ) ) ? $views[ $view_id ]['label'] : '';
		}

		return $label;
	}

	/**
	 * Determine the current view from the callbacks of each view definition.
	 *
	 * @since x.x.x.
	 *
	 * @return string|null
	 */
	public function get_current_view() {
		// Make sure we're not doing it wrong.
		if ( ! did_action( 'template_redirect' ) ) {
			$this->compatibility()->doing_it_wrong(
				__FUNCTION__,
				__( 'View cannot be accurately determined until after the <code>template_redirect</code> action has run.', 'make' ),
				'1.7.0'
			);

			return null;
		}

		$views = $this->get_sorted_views();
		$view = $this->default_view;

		foreach ( $views as $view_id => $view_args ) {
			if ( is_callable( $view_args['callback'] ) && true === call_user_func( $view_args['callback'] ) ) {
				$view = $view_id;
			}
		}

		// Check for deprecated filter.
		if ( has_filter( 'make_get_view' ) ) {
			$this->compatibility()->deprecated_hook(
				'make_get_view',
				'1.7.0',
				__( 'To add or modify theme views, use the function make_add_view() instead.', 'make' )
			);

			/**
			 * Allow developers to dynamically change the view.
			 *
			 * @since 1.2.3.
			 * @deprecated 1.7.0.
			 *
			 * @param string    $view                The view name.
			 * @param string    $parent_post_type    The post type for the parent post of the current post.
			 */
			$view = apply_filters( 'make_get_view', $view, $this->get_parent_post_type( get_post() ) );
		}

		return $view;
	}

	/**
	 * Check if a specified view is the current one.
	 *
	 * @since x.x.x.
	 *
	 * @param string $view_id
	 *
	 * @return bool
	 */
	public function is_current_view( $view_id ) {
		return $view_id === $this->get_current_view();
	}

	/**
	 * Determine if the current view is "post".
	 *
	 * The "post" view includes the standard post along with all public custom post types and attachments that are
	 * children of these post types.
	 *
	 * @since x.x.x.
	 *
	 * @return bool
	 */
	private function callback_post() {
		// Post types
		$post_types = get_post_types(
			array(
				'public' => true,
				'_builtin' => false
			)
		);
		$post_types[] = 'post';

		return is_singular( $post_types ) || ( is_attachment() && in_array( $this->get_parent_post_type( get_post() ), $post_types ) );
	}

	/**
	 * Determine if the current view is "page".
	 *
	 * The "page" view includes the page post type and attachments that are children of that post type.
	 *
	 * @since x.x.x.
	 *
	 * @return bool
	 */
	private function callback_page() {
		return is_page() || ( is_attachment() && 'page' === $this->get_parent_post_type( get_post() ) );
	}

	/**
	 * Get the post type of a post's parent.
	 *
	 * @since x.x.x.
	 *
	 * @param WP_Post $post
	 *
	 * @return false|string
	 */
	private function get_parent_post_type( WP_Post $post ) {
		return get_post_type( $post->post_parent );
	}
}