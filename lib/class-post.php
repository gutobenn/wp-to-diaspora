<?php
/**
 * A diaspora* flavoured WP Post class.
 *
 * @package WP_To_Diaspora\Post
 * @since 1.5.0
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || exit;

/**
 * Custom diaspora* post class to manage all post related things.
 *
 * @since 1.5.0
 */
class WP2D_Post {

	/**
	 * The original post object.
	 *
	 * @var WP_Posts
	 * @since 1.5.0
	 */
	public $post = null;

	/**
	 * The original post ID.
	 *
	 * @var int
	 * @since 1.5.0
	 */
	public $ID = null;

	/**
	 * If this post should be shared on diaspora*.
	 *
	 * @var bool
	 * @since 1.5.0
	 */
	public $post_to_diaspora = null;

	/**
	 * If a link back to the original post should be added.
	 *
	 * @var bool
	 * @since 1.5.0
	 */
	public $fullentrylink = null;

	/**
	 * What content gets posted.
	 *
	 * @var string
	 * @since 1.5.0
	 */
	public $display = null;

	/**
	 * The types of tags to post. (global,custom,post)
	 *
	 * @var array
	 * @since 1.5.0
	 */
	public $tags_to_post = null;

	/**
	 * The post's custom tags.
	 *
	 * @var array
	 * @since 1.5.0
	 */
	public $custom_tags = null;

	/**
	 * Aspects this post gets posted to.
	 *
	 * @var array
	 * @since 1.5.0
	 */
	public $aspects = null;

	/**
	 * Services this post gets posted to.
	 *
	 * @var array
	 * @since 1.5.0
	 */
	public $services = null;


	/**
	 * The post's history of diaspora* posts.
	 *
	 * @var array
	 * @since 1.5.0
	 */
	public $post_history = null;

	/**
	 * If the post actions have all been set up already.
	 *
	 * @var boolean
	 * @since 1.5.0
	 */
	private static $_is_set_up = false;

	/**
	 * Setup all the necessary WP callbacks.
	 *
	 * @since 1.5.0
	 */
	public static function setup() {
		if ( self::$_is_set_up ) {
			return;
		}

		$instance = new WP2D_Post( null );

		// Notices when a post has been shared or if it has failed.
		add_action( 'admin_notices', array( $instance, 'admin_notices' ) );
		add_action( 'admin_init', array( $instance, 'ignore_post_error' ) );

		// Handle diaspora* posting when saving the post.
		add_action( 'save_post', array( $instance, 'post' ), 20, 2 );
		add_action( 'save_post', array( $instance, 'save_meta_box_data' ), 10 );

		// Add meta boxes.
		add_action( 'add_meta_boxes', array( $instance, 'add_meta_boxes' ) );

		self::$_is_set_up = true;
	}

	/**
	 * Constructor.
	 *
	 * @since 1.5.0
	 *
	 * @param int|WP_Post $post Post ID or the post itself.
	 */
	public function __construct( $post ) {
		$this->_assign_wp_post( $post );
	}

	/**
	 * Assign the original WP_Post object and all the custom meta data.
	 *
	 * @since 1.5.0
	 *
	 * @param int|WP_Post $post Post ID or the post itself.
	 */
	private function _assign_wp_post( $post ) {
		if ( $this->post = get_post( $post ) ) {
			$this->ID = $this->post->ID;

			$options = WP2D_Options::instance();

			// Assign all meta values, expanding non-existent ones with the defaults..
			$meta = wp_parse_args(
				get_post_meta( $this->ID, '_wp_to_diaspora', true ),
				$options->get_options()
			);
			if ( $meta ) {
				foreach ( $meta as $key => $value ) {
					$this->$key = $value;
				}
			}

			$this->post_history = get_post_meta( $this->ID, '_wp_to_diaspora_post_history', true );
		}
	}

	/**
	 * Post to diaspora* when saving a post.
	 *
	 * @since 1.5.0
	 *
	 * @param integer $post_id ID of the post being saved.
	 * @param WP_Post $post    Post object being saved.
	 * @return boolean If the post was posted successfully.
	 */
	public function post( $post_id, $post ) {
		$this->_assign_wp_post( $post );

		$options = WP2D_Options::instance();

		// Is this post type enabled for posting?
		if ( ! in_array( $post->post_type, $options->get_option( 'enabled_post_types' ) ) ) {
			return false;
		}

		// Make sure we're posting to diaspora* and the post isn't password protected.
		// TODO: Maybe somebody wants to share a password protected post to a closed aspect.
		if ( ! ( $this->post_to_diaspora && 'publish' === $post->post_status && '' === $post->post_password ) ) {
			return false;
		}

		$status_message = $this->_get_title_link();

		// Post the full post text or just the excerpt?
		if ( 'full' === $this->display ) {
			$status_message .= $this->_get_full_content();
		} else {
			$status_message .= $this->_get_excerpt_content();
		}

		// Add the tags assigned to the post.
		$status_message .= $this->_get_tags_to_add();

		// Add the original entry link to the post?
		$status_message .= $this->_get_posted_at_link();

		$status_markdown = new HTML_To_Markdown( $status_message );
		$status_message  = $status_markdown->output();

		// Set up the connection to diaspora*.
		$conn = WP2D_Helpers::api_quick_connect();
		if ( ! empty( $status_message ) ) {
			if ( $conn->last_error ) {
				// Save the post error as post meta data, so we can display it to the user.
				update_post_meta( $post_id, '_wp_to_diaspora_post_error', $conn->last_error );
				return false;
			}

			// Add services to share to via diaspora*.
			$extra_data = array(
				'services' => $this->services,
			);

			// Try to post to diaspora*.
			if ( $response = $conn->post( $status_message, $this->aspects, $extra_data ) ) {
				// Save certain diaspora* post data as meta data for future reference.
				$this->_save_to_history( (object) $response );

				// If there is still a previous post error around, remove it.
				delete_post_meta( $post_id, '_wp_to_diaspora_post_error' );
			}
		} else {
			return false;
		}
	}

	/**
	 * Get the title of the post linking to the post itself.
	 *
	 * @since 1.5.0
	 *
	 * @return string Post title as a link.
	 */
	private function _get_title_link() {
		return sprintf(
			'<p><b><a href="%1$s" title="permalink to %2$s">%2$s</a></b></p>',
			get_permalink( $this->ID ),
			$this->post->post_title
		);
	}

	/**
	 * Get the full post content with only default filters applied.
	 *
	 * @since 1.5.0
	 *
	 * @return string The full post content.
	 */
	private function _get_full_content() {
		// Disable all filters and then enable only defaults. This prevents additional filters from being posted to diaspora*.
		remove_all_filters( 'the_content' );
		foreach ( array( 'wptexturize', 'convert_smilies', 'convert_chars', 'wpautop', 'shortcode_unautop', 'prepend_attachment', array( $this, 'embed_remove' ) ) as $filter ) {
			add_filter( 'the_content', $filter );
		}
		// Extract URLs from [embed] shortcodes.
		add_filter( 'embed_oembed_html', array( $this, 'embed_url' ), 10, 2 );

		return apply_filters( 'the_content', $this->post->post_content );
	}

	/**
	 * Get the post's excerpt in a nice format.
	 *
	 * @since 1.5.0
	 *
	 * @return string Post's excerpt.
	 */
	private function _get_excerpt_content() {
		// Look for the excerpt in the following order:
		// 1. Custom post excerpt.
		// 2. Text up to the <!--more--> tag.
		// 3. Manually trimmed content.
		$content = $this->post->post_content;
		$excerpt = $this->post->post_excerpt;
		if ( '' === $excerpt ) {
			if ( $more_pos = strpos( $content, '<!--more' ) ) {
				$excerpt = substr( $content, 0, $more_pos );
			} else {
				$excerpt = wp_trim_words( $content, 42, '[...]' );
			}
		}
		return '<p>' . $excerpt . '</p>';
	}

	/**
	 * Get a string of tags that have been added to the post.
	 *
	 * @since 1.5.0
	 *
	 * @return string Tags added to the post.
	 */
	private function _get_tags_to_add() {
		$options = WP2D_Options::instance();
		$tags_to_post = $this->tags_to_post;
		$custom_tags  = $this->custom_tags;
		$tags_to_add  = '';

		// Add any diaspora* tags?
		if ( ! empty( $tags_to_post ) ) {
			// The diaspora* tags to add to the post.
			$diaspora_tags = array();

			// Add global tags?
			if ( in_array( 'global', $tags_to_post ) ) {
				$diaspora_tags += array_flip( $options->get_option( 'global_tags' ) );
			}

			// Add custom tags?
			if ( in_array( 'custom', $tags_to_post ) ) {
				$diaspora_tags += array_flip( $custom_tags );
			}

			// Add post tags?
			if ( in_array( 'post', $tags_to_post ) ) {
				// Clean up the post tags.
				$diaspora_tags += array_flip( wp_get_post_tags( $this->ID, array( 'fields' => 'slugs' ) ) );
			}

			// Get an array of cleaned up tags.
			// NOTE: Validate method needs a variable, as it's passed by reference!
			$diaspora_tags = array_keys( $diaspora_tags );
			$options->validate_tags( $diaspora_tags );

			// Get all the tags and list them all nicely in a row.
			$diaspora_tags_clean = array();
			foreach ( $diaspora_tags as $tag ) {
				$diaspora_tags_clean[] = '#' . $tag;
			}

			// Add all the found tags.
			if ( ! empty( $diaspora_tags_clean ) ) {
				$tags_to_add = implode( ' ', $diaspora_tags_clean ) . '<br />';
			}
		}

		return $tags_to_add;
	}

	/**
	 * Get the link to the original post.
	 *
	 * @since 1.5.0
	 *
	 * @return string Original post link.
	 */
	private function _get_posted_at_link() {
		$link = '';
		if ( $this->fullentrylink ) {
			$link = sprintf( '%1$s [%2$s](%2$s "%3$s")',
				__( 'Originally posted at:', 'wp-to-diaspora' ),
				get_permalink( $this->ID ),
				$this->post->post_title
			);
		}

		return $link;
	}

	/**
	 * Save the details of the new diaspora* post to this post's history.
	 *
	 * @since 1.5.0
	 *
	 * @param object $response Response from the API containing the diaspora* post details.
	 */
	private function _save_to_history( $response ) {
		// Make sure the post history is an array.
		if ( empty( $this->post_history ) ) {
			$this->post_history = array();
		}

		// Add a new entry to the history.
		$this->post_history[] = array(
			'id'         => $response->id,
			'guid'       => $response->guid,
			'created_at' => $this->post->post_modified,
			'aspects'    => $this->aspects,
			'nsfw'       => $response->nsfw,
			'post_url'   => $response->permalink,
		);

		update_post_meta( $this->ID, '_wp_to_diaspora_post_history', $this->post_history );
	}

	/**
	 * Return URL from [embed] shortcode instead of generated iframe.
	 *
	 * @since 1.5.0
	 * @see WP_Embed::shortcode()
	 *
	 * @param mixed  $html The cached HTML result, stored in post meta.
	 * @param string $url  The attempted embed URL.
	 * @return string URL of the embed.
	 */
	public function embed_url( $html, $url ) {
		return $url;
	}

	/**
	 * Removes '[embed]' and '[/embed]' left by embed_url.
	 *
	 * @since 1.5.0
	 * @todo It would be great to fix it using only one filter.
	 *       It's happening because embed filter is being removed by remove_all_filters('the_content') on WP2D_Post::post().
	 *
	 * @param string $content Content of the post.
	 * @return string The content with the embed tags removed.
	 */
	public function embed_remove( $content ) {
		return str_replace( array( '[embed]', '[/embed]' ), array( '<p>', '</p>' ), $content );
	}


	/*
	 * META BOX
	 */

	/**
	 * Adds a meta box to the main column on the enabled Post Types' edit screens.
	 *
	 * @since 1.5.0
	 */
	public function add_meta_boxes() {
		$options = WP2D_Options::instance();
		foreach ( $options->get_option( 'enabled_post_types' ) as $post_type ) {
			add_meta_box(
				'wp_to_diaspora_meta_box',
				'WP to diaspora*',
				array( $this, 'meta_box_render' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	/**
	 * Prints the meta box content.
	 *
	 * @since 1.5.0
	 *
	 * @param WP_Post $post The object for the current post.
	 */
	public function meta_box_render( $post ) {
		$this->_assign_wp_post( $post );

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'wp_to_diaspora_meta_box', 'wp_to_diaspora_meta_box_nonce' );

		// Get the default values to use, but give priority to the meta data already set.
		$options = WP2D_Options::instance();

		// Make sure we have some value for post meta fields.
		$this->custom_tags = $this->custom_tags ?: array();

		// If this post is already published, don't post again to diaspora* by default.
		$this->post_to_diaspora = ( $this->post_to_diaspora && 'publish' !== get_post_status( $this->ID ) );
		$this->aspects          = $this->aspects  ?: array();
		$this->services         = $this->services ?: array();

		// Have we already posted on diaspora*?
		if ( is_array( $this->post_history ) ) {
			$latest_post = end( $this->post_history );
			?>
			<p><a href="<?php echo esc_attr( $latest_post['post_url'] ); ?>" target="_blank"><?php esc_html_e( 'Already posted to diaspora*.', 'wp-to-diaspora' ); ?></a></p>
			<?php
		}
		?>

		<p><?php $options->post_to_diaspora_render( $this->post_to_diaspora ); ?></p>
		<p><?php $options->fullentrylink_render( $this->fullentrylink ); ?></p>
		<p><?php $options->display_render( $this->display ); ?></p>
		<p><?php $options->tags_to_post_render( $this->tags_to_post ); ?></p>
		<p><?php $options->custom_tags_render( $this->custom_tags ); ?></p>
		<p><?php $options->aspects_services_render( array( 'aspects', $this->aspects ) ); ?></p>
		<p><?php $options->aspects_services_render( array( 'services', $this->services ) ); ?></p>

		<?php
	}

	/**
	 * When the post is saved, save our meta data.
	 *
	 * @since 1.5.0
	 *
	 * @param integer $post_id The ID of the post being saved.
	 */
	public function save_meta_box_data( $post_id ) {
		/*
		 * We need to verify this came from our screen and with proper authorization,
		 * because the save_post action can be triggered at other times.
		 */
		if ( ! $this->_is_safe_to_save() ) {
			return;
		}

		/* OK, it's safe for us to save the data now. */

		// Meta data to save.
		$meta_to_save = $_POST['wp_to_diaspora_settings'];
		$options = WP2D_Options::instance();

		// Checkboxes.
		$options->validate_checkboxes( array( 'post_to_diaspora', 'fullentrylink' ), $meta_to_save );

		// Single Selects.
		$options->validate_single_selects( 'display', $meta_to_save );

		// Multiple Selects.
		$options->validate_multi_selects( 'tags_to_post', $meta_to_save );

		// Save custom tags as array.
		$options->validate_tags( $meta_to_save['custom_tags'] );

		// Clean up the list of aspects. If the list is empty, only use the 'Public' aspect.
		$options->validate_aspects_services( $meta_to_save['aspects'], array( 'public' ) );

		// Clean up the list of services.
		$options->validate_aspects_services( $meta_to_save['services'] );

		// Update the meta data for this post.
		update_post_meta( $post_id, '_wp_to_diaspora', $meta_to_save );
	}

	/**
	 * Perform all checks to see if we are allowed to save the meta data.
	 *
	 * @since 1.5.0
	 *
	 * @return boolean If the verification checks have passed.
	 */
	private function _is_safe_to_save() {
		// Verify that our nonce is set and  valid.
		if ( ! ( isset( $_POST['wp_to_diaspora_meta_box_nonce'] ) && wp_verify_nonce( $_POST['wp_to_diaspora_meta_box_nonce'], 'wp_to_diaspora_meta_box' ) ) ) {
			return false;
		}

		// If this is an autosave, our form has not been submitted, so we don't want to do anything.
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return false;
		}

		// Check the user's permissions.
		$permission = ( isset( $_POST['post_type'] ) && 'page' === $_POST['post_type'] ) ? 'edit_pages' : 'edit_posts';
		if ( ! current_user_can( $permission, $this->ID ) ) {
			return false;
		}

		// Make real sure that we have some meta data to save.
		if ( ! isset( $_POST['wp_to_diaspora_settings'] ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Add admin notices when a post gets displayed.
	 *
	 * @since 1.5.0
	 */
	public function admin_notices() {
		global $post, $pagenow;
		if ( ! $post || 'post.php' !== $pagenow ) {
			return;
		}

		if ( $error = get_post_meta( $post->ID, '_wp_to_diaspora_post_error', true ) ) {
			// This notice will only be shown if posting to diaspora* has failed.
			printf( '<div class="error notice is-dismissible"><p>%1$s %2$s <a href="%3$s">%4$s</a></p></div>',
				esc_html__( 'Failed to post to diaspora*.', 'wp-to-diaspora' ),
				esc_html( $error ),
				esc_url( add_query_arg( 'wp_to_diaspora_ignore_post_error', 'yes' ) ),
				esc_html__( 'Ignore', 'wp-to-diaspora' )
			);
		} elseif ( ( $diaspora_post_history = get_post_meta( $post->ID, '_wp_to_diaspora_post_history', true ) ) && is_array( $diaspora_post_history ) ) {
			// Get the latest post from the history.
			$latest_post = end( $diaspora_post_history );

			// Only show if this post is showing a message and the post is a fresh share.
			if ( isset( $_GET['message'] ) && $post->post_modified === $latest_post['created_at'] ) {
				printf( '<div class="updated notice is-dismissible"><p>%1$s <a href="%2$s" target="_blank">%3$s</a></p></div>',
					esc_html__( 'Successfully posted to diaspora*.', 'wp-to-diaspora' ),
					esc_url( $latest_post['post_url'] ),
					esc_html__( 'View Post' )
				);
			}
		}
	}

	/**
	 * Delete the error post meta data if it gets ignored.
	 *
	 * @since 1.5.0
	 */
	public function ignore_post_error() {
		// If "Ignore" link has been clicked, delete the post error meta data.
		if ( isset( $_GET['wp_to_diaspora_ignore_post_error'], $_GET['post'] ) && 'yes' === $_GET['wp_to_diaspora_ignore_post_error'] ) {
			delete_post_meta( $_GET['post'], '_wp_to_diaspora_post_error' );
		}
	}
}