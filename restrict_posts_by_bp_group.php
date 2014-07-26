<?php
/*
Plugin Name: Restrict Posts By Buddypress Group
Plugin URI: http://bappi-d-great.com
Description: Restrict your posts based on BuddyPress groups
Version: 1.0.1
Author: Bappi D Great
Author URI: http://bappi-d-great.com
License: GPLv2 or later
Domain: rpbg
*/

if ( ! defined( 'ABSPATH' ) ) exit;

if( ! class_exists( 'RPBG' ) ) {
    
    class RPBG {
        
        public $types;
        public $restricted_posts_IDs;
        
        public function __construct() {
            
            $this->restricted_posts_IDs = array();
            
            add_action( 'admin_notices', array( $this, 'rpbg_admin_notice' ) );
            add_action( 'add_meta_boxes', array( $this, 'rpbg_add_meta_box' ) );
            add_action( 'save_post', array( $this, 'rpbg_save_meta_box_data' ) );
            add_action( 'pre_get_posts', array( $this, 'rpbg_filter_posts' ) );
            add_action( 'init', array( $this, 'get_restricted_post_IDs' ) );
            
        }
        
        public function rpbg_admin_notice() {
            global $bp;
            
            if( ! $bp ){
                ?>
                <div class="error">
                    <p><?php _e( sprintf( 'Restrict Posts By Buddypress Group needs BuddyPress plugin activated. Please <a href="%s">activate BuddyPress</a>.', admin_url( 'plugins.php' ) ), 'rpbg' ) ?></p>
                </div>
                <?php
            }
        }
        
        public function rpbg_add_meta_box() {
            foreach( $this->types as $type )
                add_meta_box( 'bp_group_list', __( 'Select Restricted Groups', 'rpbg' ), array( $this, 'bp_group_meta_box' ), $type, 'normal', 'high' );
        }
        
        public function bp_group_meta_box( $post ) {
            wp_nonce_field( 'rpb_meta_box', 'rpbg_meta_box_nonce' );
            $grps = explode( ',', get_post_meta( $post->ID, '_rpbg_meta_value_key', true ) );
            ?>
            <p><?php _e( 'Select restricted bp groups from the following:', 'rpbg' ) ?></p>
            <?php
                $groups = BP_Groups_Group::get();
                foreach( $groups['groups'] as $group ){
            ?>
            <label>
                <input <?php echo in_array( $group->id, $grps ) ? 'checked' : '' ?> type="checkbox" name="bp_res_grp_post[]" value="<?php echo $group->id ?>"> <?php echo $group->name; ?>
            </label>
            <?php } ?>
            <?php
            
        }
        
        public function rpbg_save_meta_box_data( $post_id ) {
            
            if ( isset( $_POST['post_type'] ) && ! in_array( $_POST['post_type'], $this->types ) ) {
                return;
            }
            
            if ( ! isset( $_POST['rpbg_meta_box_nonce'] ) ) {
		return;
            }
            
            if ( ! wp_verify_nonce( $_POST['rpbg_meta_box_nonce'], 'rpb_meta_box' ) ) {
		return;
            }
            
            if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
            }
            
            $grp = implode( ',', $_POST['bp_res_grp_post'] );
            update_post_meta( $post_id, '_rpbg_meta_value_key', $grp );
            
        }
        
        public function rpbg_filter_posts( $query ) {
            if( ! is_user_logged_in() ) {
                $query->set('meta_query', array(
                                            array(
                                                    'key' => '_rpbg_meta_value_key',
                                                    'compare' => 'NOT EXISTS'
                                                )
                                            ));
            }else{
                if( ! is_admin() ) {
                    $query->set( 'post__not_in', $this->restricted_posts_IDs );
                }
                
            }
        }
        
        public function get_restricted_post_IDs() {
            $this->types = apply_filters( 'post_type_for_rpbg', array( 'post' ) );
            $groups = BP_Groups_Member::get_group_ids( get_current_user_id() );
            $args = array(
                          'post_type' => $this->types,
                          'post_status' => 'publish',
                          'posts_per_page' => -1
                          );
            $posts = get_posts( $args );
            foreach( $posts as $post ) {
                $grps = explode( ',', get_post_meta( $post->ID, '_rpbg_meta_value_key', true ) );
                $result = array_intersect( $groups['groups'], $grps );
                if( count( $result ) > 0 ) {
                    array_push( $this->restricted_posts_IDs, $post->ID );
                }
            }
        }
        
        
    }
    
    $RPBG = new RPBG();
}
