<?php
/**
 * @package media2post
 * @version 1.0
 */
/*
Plugin Name: Phototools: media2post
Plugin URI: https://gerhardhoogterp.nl/plugins/
Description: Create posts from the media list screen. Single or in batch. Also adds MediaRSS to your feeds.
Author: Gerhard Hoogterp
Version: 1.0
Author URI: https://gerhardhoogterp.nl/
*/

if ( ! defined( 'WPINC' ) ) {
	die;
}

class media2post_class {

        const FS_TEXTDOMAIN = 'media2post';
	const FS_PLUGINID   = 'media2post';

        public function __construct() {
            $media2post_options = get_option( 'media2post_options' );
            
            register_activation_hook( __FILE__,         array( $this, 'activate' ) );
            register_deactivation_hook( __FILE__,	array( $this, 'deactivate' ) );
            add_action( 'init',                         array($this,'myTextDomain'));
            add_filter( 'plugin_row_meta',              array($this,'PluginLinks'),10,2);
            add_action( 'admin_init',                   array($this,'register_settings'));
            add_action( 'admin_menu',                   array($this,'add_phototools_menuitem'));

            add_action( 'wp_ajax_media2postHandler',    array($this,'ajaxMedia2postHandler'));
            add_action( 'before_delete_post',           array($this,'remove_attachment_with_post', 10));
            add_filter( 'media_row_actions',            array($this,'create_post_from_media_link'), 10, 3);
            
            add_action( 'bulk_actions-upload',          array($this,'media2post_bulk_actions'));
            add_action( 'handle_bulk_actions-upload',   array($this,'media2post_bulk_actions_handle'), 10, 3 );

            if ($media2post_options['doMediaRSS']):

                if ($media2post_options['addImage']):
                   add_filter('the_excerpt_rss',           array($this,'add_feed_content'));
                endif;
                
		add_action( "rss2_ns",			array($this,"feed_addNameSpace") );
		add_action( "rss_item",			array($this,"feed_addMediaRSS"));
		add_action( "rss2_item",		array($this,"feed_addMediaRSS"));
            endif;            
            
        }	

	public function myTextDomain() {
		load_plugin_textdomain(
			self::FS_TEXTDOMAIN,
			false,
			dirname(plugin_basename(__FILE__)).'/languages/'
		);
	}	

        function PluginLinks($links, $file) {
            if ( strpos( $file, self::FS_PLUGINID.'.php' ) !== false ) {
                $links[]='<a href="'.admin_url().'admin.php?page=phototools">'.__('General info',self::FS_TEXTDOMAIN).'</a>';
                $links[]='<a href="'.admin_url().'admin.php?page='.self::FS_PLUGINID.'">'.__('Settings',self::FS_TEXTDOMAIN).'</a>';
            
            }
            return $links;
        }	
	
	/**
	 * defaults are assigned if this is the first time.
	 */
	public function activate() {
                $phototools = get_option( 'phototools_list');
                $phototools[self::FS_PLUGINID] = plugin_basename( __FILE__ );
                update_option('phototools_list',$phototools);
	
		$media2post_options = get_option( 'media2post_options' );
		if (empty($media2post_options)):
                        $media2post_options['default_post_status']  = 'private';
                        $media2post_options['default_post_tags']    = 'media2post';
                        $media2post_options['doMediaRSS']           = true;
                        $media2post_options['addImage']             = true;
                        $media2post_options['default_post_copyright']  = '&copy; Copyrights reserved ';
                        $media2post_options['postedFirstLine']       = 'Posted first on '.get_bloginfo('name');
                        update_option( 'media2post_options', $media2post_options );
                endif;
                
                }

        public function deactivate() {
                $phototools = get_option( 'phototools_list');
                $self = self::FS_PLUGINID;
                unset($phototools[$self]);
                if (!empty($phototools)):
                    update_option('phototools_list',$phototools);
                else: 
                    delete_option('phototools_list');
                endif;
	}	

        function add_phototools_menuitem() {
                if (empty ( $GLOBALS['admin_page_hooks']['phototools'] )): 
                        add_menu_page( 
				__('Phototools',self::FS_TEXTDOMAIN ), 
				__('Phototools', self::FS_TEXTDOMAIN), 
				'manage_options', 
				'phototools', 
				array($this,'phototools_info'),
				'dashicons-camera',
				25
			);
                    endif;
                $this->create_submenu_item();    
        }	
	
        public function phototools_info() {
		?>
			<div class="wrap">
				<div class="icon32" id="icon-options-general"></div>
				<h1><?php _e( 'Phototools', self::FS_TEXTDOMAIN); ?></h1>

				<p>
				<?php _e('Phototools is a collection of plugins which add functionality for those
				who use WordPress to run a photoblog or gallery site or something alike.',self::FS_TEXTDOMAIN);
				?>
				</p>
                                <p><?php _e('The following plugins in this series are installed:',self::FS_TEXTDOMAIN);?></p>
                                <?php
                                    $phototools = get_option( 'phototools_list');
                                    foreach($phototools as $id => $shortPath):
                                        $plugin = get_plugin_data(WP_PLUGIN_DIR .'/'. $shortPath,true);
                                        ?>
                                        <div class="card">
                                        <h3><a href="<?php echo $plugin['PluginURI'];?>" target="_blank"><?php echo $plugin['Name'].' '.$plugin['Version'];?></a></h3>
                                        <p><?php echo $plugin['Description'];?></p>
                                        </div>
                                        <?php
                                    endforeach;
                                ?>

			</div>
		<?php
	}	
	
// ---------------------------------------------------------------------------------------------------

        function create_post_from_media_link($actions, $post, $detached) {
            if( ! wp_attachment_is_image( $post->ID ) )
                return;
                
            $parentID = wp_get_post_parent_id($post->ID);
            if (!$parentID):
                $nonce = wp_create_nonce("media2post_nonce");
                $link = admin_url('admin-ajax.php?action=media2postHandler&post_id='.$post->ID.'&nonce='.$nonce);
                $actions['create_post_link'] = '<a href="'.$link.'">Create post</a>';
            endif;

            return $actions;
        }

        private function media2postHandler($media_id) {

            $media2post_options = get_option( 'media2post_options' );
            $media_data = get_post($media_id);

            $args = array(
		'post_title'    => $media_data->post_title,
		'post_content'  => $media_data->post_content,
		'post_status'   => $media2post_options['default_post_status'],
		'post_author'   => $media_data->post_author,
		'post_date'     => $media_data->post_date,
		'post_format'   => $media2post_options['default_post_format'],
		'post_category' => array( get_option('default_category') ),
		'post_type'     => $media2post_options['default_post_type'],
		'tags_input'    => $media2post_options['default_post_tags']
            );
            
            $new_post_id = wp_insert_post( $args );
            
            if(is_wp_error($new_post_id)):
                wp_die();
            endif;
            
            update_post_meta( $new_post_id, '_thumbnail_id', $media_id );
            wp_update_post( array(
			'ID'          => $media_id,
			'post_parent' => $new_post_id,
			'post_status' => 'inherit',
		) );        
        }


        function ajaxMedia2postHandler() {
            if ( !wp_verify_nonce( $_REQUEST['nonce'], "media2post_nonce")) {
                exit("Nah, don't think so...");
            }
            $this->media2postHandler($_REQUEST['post_id']);
            
            if(!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
                $result = json_encode($result);
                echo $result;
                } else {
                header("Location: ".$_SERVER["HTTP_REFERER"]);
            }
            
            wp_die(); // this is required to terminate immediately and return a proper response
        }


// clear attachment before deleting a post
        public function remove_attachment_with_post($post_id) {
            if(has_post_thumbnail( $post_id )):
                $attachment_id = get_post_thumbnail_id( $post_id );
                wp_update_post( array(
                    'ID'          => $attachment_id,
                    'post_parent' => 0			
                ) );
                do_action( 'w3tc_flush_objectcache' ); 
            endif;
        }

// options            
        public function create_submenu_item() {
       
            add_submenu_page( 
                'phototools',
                __('Add "create" option to unattached media', self::FS_TEXTDOMAIN ), 
                __('Media2post', self::FS_TEXTDOMAIN), 
                'manage_options', 
                'media2post', 
                array( $this, 'view_settings' ) );
        }

        public function view_settings() {
            ?>
            <div class="wrap">
                    <div class="icon32" id="icon-options-general"></div>
                    <h2><?php _e( 'media2post', self::FS_TEXTDOMAIN); ?></h2>
                    <h3><?php _e( 'Overview', self::FS_TEXTDOMAIN ); ?></h3>
                    <p style="margin-left: 12px;max-width: 640px;"><?php _e('The default <strong>post status</strong> is set to <strong>publish</strong> by default. This means that as soon as you upload a new image through any interface in WordPress, a new post will appear with that image assigned as the featured image.', self::FS_TEXTDOMAIN ); ?></p>
                    <p style="margin-left: 12px;max-width: 640px;"><?php _e('The default <strong>post type</strong> is set to the most familiar WordPress post type, <strong>Post</strong>. Other custom post types registered by your theme and installed plugins have been automatically detected and will also appear in the drop down menu as options. Note that these custom post types should have support for featured images, or they may not appear as you would like.', self::FS_TEXTDOMAIN ); ?></p>
            
			
                    <form method="POST" action="options.php">
     
                        <?php
			settings_fields( 'media2post_options' );
			do_settings_sections( 'media2post' );
                        ?>
     
                    <p class="submit"><input type="submit" class="button-primary" value="<?php _e( 'Save Changes', self::FS_TEXTDOMAIN ); ?>"></p>
                    </form>
            </div>
            <?php
    }
    

    public function validate_options( $input ) {
            global $_wp_theme_features;
            $valid_post_status_options = array( 'draft', 'publish', 'private' );
            $valid_post_type_options = get_post_types( array( '_builtin' => false ) );
            $valid_post_type_options[] = $this->post_type;
            $valid_post_format_options = array( $this->post_format );

            if ( isset( $_wp_theme_features['post-formats'] ) && is_array( $_wp_theme_features['post-formats'] ) ):
                    foreach ( $_wp_theme_features['post-formats'] as $post_format_array ):
                            foreach ( $post_format_array as $post_format ):
                                    $valid_post_format_options[] = $post_format;
                            endforeach;
                    endforeach;
            endif;

            if ( ! in_array( $input['default_post_status'], $valid_post_status_options ) ):
                    $input['default_post_status'] = $this->post_status;
            endif;

            if ( ! in_array( $input['default_post_type'], $valid_post_type_options ) ):
                    $input['default_post_type'] = $this->post_type;
            endif;

            if ( isset( $input['default_post_format'] ) && ! in_array( $input['default_post_format'], $valid_post_format_options )):
                    $input['default_post_format'] = $this->post_format;
            endif;

            if ( ! isset( $input['default_post_tags'])):
                $input['default_post_tags'] = 'media2post';
            endif;
            
            
            
            
            return $input;
	}    
    
    
// Register our settings and add the settings sections and fields.
    public function register_settings() {
            register_setting( 'media2post_options', 'media2post_options', array( $this, 'validate_options' ) );

            add_settings_section( 'media2post_section_main', '', array( $this, 'output_section_text' ), 'media2post' );

            add_settings_field( 'media2post_default_post_status', __( 'Default Post Status:', self::FS_TEXTDOMAIN ), array( $this, 'output_default_post_status_text' ), 'media2post', 'media2post_section_main' );
            add_settings_field( 'media2post_default_post_type',   __( 'Default Post Type:',   self::FS_TEXTDOMAIN ), array( $this, 'output_default_post_type_text'   ), 'media2post', 'media2post_section_main' );

            if ( current_theme_supports( 'post-formats' ) ):
                    add_settings_field( 'media2post_default_post_format', __( 'Default Post Format:', self::FS_TEXTDOMAIN ), array( $this, 'output_default_post_format_text' ), 'media2post', 'media2post_section_main' );
            endif;
            add_settings_field( 'media2post_default_tags', __( 'Default Tags:',self::FS_TEXTDOMAIN ), array( $this, 'output_default_post_tags' ), 'media2post', 'media2post_section_main' );

            
            add_settings_section( 'media2post_section_MediaRSS', 'MediaRSS settings', array( $this, 'output_section_text' ), 'media2post' );            
            add_settings_field( 'media2post_doMediaRSS', __( 'Add MediaRSS:',self::FS_TEXTDOMAIN ), array( $this, 'output_default_post_doMediaRSS_CB' ), 'media2post', 'media2post_section_MediaRSS' );
            add_settings_field( 'media2post_addImageToContent', __( 'Add thumbnail to description:',self::FS_TEXTDOMAIN ), array( $this, 'output_default_post_addImage_CB' ), 'media2post', 'media2post_section_MediaRSS' );
            
            add_settings_field( 'media2post_default_copyright', __( 'Copyright line for feed:',self::FS_TEXTDOMAIN ), array( $this, 'output_default_post_copyright' ), 'media2post', 'media2post_section_MediaRSS' );
            add_settings_field( 'media2post_default_postedFirst', __( '"Posted first" line:',self::FS_TEXTDOMAIN ), array( $this, 'output_default_post_postedFirst' ), 'media2post', 'media2post_section_MediaRSS' );
            
    }

   function option($option,$text,$value) {
        if (is_array($value)):
            $selected=in_array($option,$value)?' selected':'';
        else:
            $selected=$option===$value?' selected':'';
        endif;
        printf('<option value="%s"%s>%s</option>',$option,$selected,$text);
   }
    
    
    public function output_default_post_doMediaRSS_CB() {
        $media2post_options = get_option( 'media2post_options' );
	print '<input type="checkbox" id="doMediaRSS" name="media2post_options[doMediaRSS]" '.($media2post_options['doMediaRSS']?'checked':'').' >';
    }
 
    public function output_default_post_addImage_CB() {
        $media2post_options = get_option( 'media2post_options' );
	print '<input type="checkbox" id="addImage" name="media2post_options[addImage]" '.($media2post_options['addImage']?'checked':'').' >';
    }    
    
    public function output_section_text() { }

    public function output_default_post_tags() { 
            $media2post_options = get_option( 'media2post_options' );
            ?>
            <input type="text" id="media2post_default_tags" name="media2post_options[default_post_tags]" value="<?php echo $media2post_options['default_post_tags']; ?>">
             <span><?php echo __('Multiple tags should be seperated by a comma',self::FS_TEXTDOMAIN); ?> </span>
            <?php
    }
    
    public function output_default_post_postedFirst() { 
            $media2post_options = get_option( 'media2post_options' );
            ?>
            <input type="text" id="postedFirstLine" name="media2post_options[postedFirstLine]" value="<?php echo $media2post_options['postedFirstLine']; ?>">
            <?php
    }
    
    
    public function output_default_post_copyright() { 
            $media2post_options = get_option( 'media2post_options' );
            ?>
            <input type="text" id="media2post_default_copyright" name="media2post_options[default_post_copyright]" value="<?php echo $media2post_options['default_post_copyright']; ?>">
            <?php
    }    
    
    public function output_default_post_type_text() {
            $media2post_options = get_option( 'media2post_options' );
            $all_post_types = get_post_types( array( '_builtin' => false ) );

            if ( ! isset( $media2post_options['default_post_type'] ) )
                    $media2post_options['default_post_type'] = $this->post_type;
            ?>
            <select id="media2post-default-post-type" name="media2post_options[default_post_type]">
                    <option value="post" <?php selected( $media2post_options['default_post_type'], 'post' ); ?>>Post</option>
            <?php foreach( $all_post_types as $p ) : ?>
                    <option value="<?php echo esc_attr( $p ); ?>" <?php selected( $media2post_options['default_post_type'], esc_attr( $p ) ); ?>><?php echo esc_html( $p ); ?></option>
            <?php endforeach; ?>
            </select>
            <?php
    }
  
    /**
	 * Output the text related to selecting the post formats to be assigned when a featured
	 * image is automatically added.
	 */
	public function output_default_post_format_text() {
		global $_wp_theme_features;
		$media2post_options = get_option( 'media2post_options' );

		if ( ! isset( $media2post_options['default_post_format'] ) )
			$media2post_options['default_post_format'] = $this->post_format;
		?>
		<select id="media2post_default_post_format" name="media2post_options[default_post_format]">
			<?php
				if ( isset( $_wp_theme_features['post-formats'] ) && is_array( $_wp_theme_features['post-formats'] ) ) {
					foreach ( $_wp_theme_features['post-formats'] as $post_format_array ){
						foreach ( $post_format_array as $post_format ) {
							?>
							<option value="<?php echo esc_attr( $post_format ); ?>" <?php selected( $media2post_options['default_post_format'], esc_attr( $post_format ) ); ?>><?php echo esc_html( ucwords( $post_format ) ); ?></option>
							<?php
						}
						if ( ! in_array( 'standard', $post_format_array ) ) {
							?>
							<option value="standard" <?php selected( $media2post_options['default_post_format'], 'standard' ); ?>>Standard</option>
							<?php
						}
					}
				} else {
					?>
					<option value="standard" <?php selected( $media2post_options['default_post_format'], 'standard' ); ?>>Standard</option>
					<?php
				}
		?>
		</select>
		<?php
	}    
    
    
    

    /**
    * Output the text related to selecting the post status to be assigned when a featured
    * image is automatically added.
    */
    public function output_default_post_status_text() {
            $media2post_options = get_option( 'media2post_options' );

            if ( ! isset( $media2post_options['default_post_status'] ) )
                    $media2post_options['default_post_status'] = $this->post_status;
            ?>
            <select id="media2post_default_post_status" name="media2post_options[default_post_status]">
                    <option value="draft"   <?php selected( $media2post_options['default_post_status'], 'draft'   ); ?>>Draft</option>
                    <option value="publish" <?php selected( $media2post_options['default_post_status'], 'publish' ); ?>>Publish</option>
                    <option value="private" <?php selected( $media2post_options['default_post_status'], 'private' ); ?>>Private</option>
            </select>
            <?php
    }    
    
// Bulk handler	
    public function media2post_bulk_actions( $actions ) {
        // Add custom bulk action
        
        $actions['media2post_create_post'] = __( 'Create post with featured image' );
        return $actions;
    }
    public function media2post_bulk_actions_handle( $redirect_to, $doaction, $post_ids ) {
        if ( $doaction == 'media2post_create_post' ) {
            foreach ( $post_ids as $post_id ) {
                $this->media2postHandler($post_id);
            }
        }
        return $redirect_to;
    }

// add MediaRSS fields to feed	
/*
<enclosure url="https://www.funsite.eu/wp-content/uploads/2018/05/img_20180420_143101-01.jpg" length="2537019" type="image/jpg"/>
<media:content url="https://www.funsite.eu/wp-content/uploads/2018/05/img_20180420_143101-01-590x589.jpg" width="590" height="589" medium="image" type="image/jpeg" xmlns:media="http://search.yahoo.com/mrss/">
<media:copyright>Copyright G. Hoogterp</media:copyright>
<media:title/>
<media:description type="html"><![CDATA[]]></media:description>
</media:content>
<media:thumbnail url="https://www.funsite.eu/wp-content/uploads/2018/05/img_20180420_143101-01-150x150.jpg" width="150" height="150" xmlns:media="http://search.yahoo.com/mrss/"/>
*/
    	function feed_addNameSpace() {
            echo 'xmlns:media="http://search.yahoo.com/mrss/"'."\n";
	}

	
/*
WP_Post Object
(
    [ID] => 82
    [post_author] => 1
    [post_date] => 2014-06-12 18:58:22
    [post_date_gmt] => 2014-06-12 16:58:22
    [post_content] => 
    [post_title] => DSC03168.darktable.800
    [post_excerpt] => 
    [post_status] => inherit
    [comment_status] => open
    [ping_status] => open
    [post_password] => 
    [post_name] => dsc03168-darktable-800
    [to_ping] => 
    [pinged] => 
    [post_modified] => 2018-05-05 18:05:12
    [post_modified_gmt] => 2018-05-05 16:05:12
    [post_content_filtered] => 
    [post_parent] => 0
    [guid] => http://www.funsite.eu/wp-content/uploads/2014/06/DSC03168.darktable.800.jpg
    [menu_order] => 0
    [post_type] => attachment
    [post_mime_type] => image/jpeg
    [comment_count] => 0
    [filter] => raw
    )
medium="image" type="<?php echo $media_data->post_mime_type;?>"                
*/	
	
        function feed_addMediaRSS() {
            $media2post_options = get_option( 'media2post_options' );
            $post_thumbnail_id = get_post_thumbnail_id( $GLOBALS['post']->ID );
            if ($post_thumbnail_id):
                $media_data = get_post($post_thumbnail_id);
                $filesize = filesize(get_attached_file( $post_thumbnail_id ));
                $thumbnail = wp_get_attachment_image_src($media_data->ID,'thumbnail');
                $medium = wp_get_attachment_image_src($media_data->ID,'medium');
?>
<enclosure url="<?php echo $media_data->guid; ?>" length="<?php echo $filesize;?>" type="<?php echo $media_data->post_mime_type;?>" />
<media:content url="<?php echo $medium[0]?>" width="<?php echo $medium[1]?>" height="<?php echo $medium[2]?>" medium="image" type="<?php echo $media_data->post_mime_type;?>" xmlns:media="http://search.yahoo.com/mrss/">
    <media:copyright><![CDATA[<?php echo htmlentities($media2post_options['default_post_copyright']);?>]]></media:copyright>
    <media:title><![CDATA[<?php echo htmlentities($media_data->post_title) ?>]]></media:title>
    <media:description type="html"><![CDATA[<?php echo htmlentities($media_data->post_excerpt); ?>]]></media:description>
</media:content>
<media:thumbnail url="<?php echo $thumbnail[0]?>" width="<?php echo $thumbnail[1]?>" height="<?php echo $thumbnail[2]?>" xmlns:media="http://search.yahoo.com/mrss/" />
<?php
            endif;
	}    
	
        function add_feed_content($content) {
            if(is_feed()) {
                $media2post_options = get_option( 'media2post_options' );
                $post_thumbnail_id = get_post_thumbnail_id( $GLOBALS['post']->ID );
                $thumbnail = wp_get_attachment_image_src($post_thumbnail_id,'medium');
                $PermaLink = get_permalink($GLOBALS['post']->ID ).'?utm_source=site&utm_medium=feed';
                $content = '<a href="'.$PermaLink.'" target="_blank" style="display:block; float: right; margin-left: 10px;margin-bottom: 10px;">'
                    .'<img src="'.$thumbnail[0].'" width="'.$thumbnail[1].'" height="'.$thumbnail[2].'" style="border:1px solid #333;" >'
                    .'</a>'.$content;
                    
                if (!empty($media2post_options['postedFirstLine'])):
                    $content .= "\n\n<p>".$media2post_options['postedFirstLine']."</p>\n";
                endif;
            }
            return $content;
        }	
    
}

$media2post = new media2post_class();
?>
