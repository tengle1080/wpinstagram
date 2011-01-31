<?php
/*
	Plugin Name: Instagram for Wordpress
	Plugin URI: http://wordpress.org/extend/plugins/instagram-for-wordpress/
	Description: Simple sidebar widget that shows Your latest 20 instagr.am pictures
	Version: 0.1.3
	Author: Eriks Remess
	Author URI: http://twitter.com/EriksRemess
*/
add_action( 'init', 'instagram_add_scripts' );
add_action( 'widgets_init', 'load_wpinstagram' );
function instagram_add_scripts(){
	if( is_active_widget( '', '', 'wpinstagram-widget' ) ) {
		wp_enqueue_script("jquery");
		wp_register_script("jquery_cycle", "http://cloud.github.com/downloads/malsup/cycle/jquery.cycle.all.latest.js");
		wp_enqueue_script("jquery_cycle");
		wp_register_script("jquery_easing", "http://static.apps.lv/fancybox/jquery.easing-1.3.pack.js");
		wp_enqueue_script("jquery_easing");
		wp_register_script("jquery_mousewhell", "http://static.apps.lv/fancybox/jquery.mousewheel-3.0.4.pack.js");
		wp_enqueue_script("jquery_mousewhell");
		wp_register_script("jquery_fancybox", "http://static.apps.lv/fancybox/jquery.fancybox-1.3.4.pack.js");
		wp_enqueue_script("jquery_fancybox");
		add_action("wp_head", "instagram_add_scripts_extra");
	}
}

function instagram_add_scripts_extra(){
	echo '<link rel="stylesheet" href="http://static.apps.lv/fancybox/jquery.fancybox-1.3.4.css" type="text/css" media="screen" />
		<script>
			jQuery(function(){
				jQuery("div.wpinstagram").cycle({fx: "fade"});
				jQuery("div.wpinstagram").find("a").fancybox({
					"transitionIn":		"elastic",
					"transitionOut":	"elastic",
					"easingIn":		"easeOutBack",
					"easingOut":		"easeInBack",
					"titlePosition":	"over",
					"padding":		0,
					"hideOnContentClick":	"true"
				});
			});
		</script>';
}

function load_wpinstagram() {
	register_widget( 'WPInstagram_Widget' );
}
class WPInstagram_Widget extends WP_Widget {
	function WPInstagram_Widget(){
		$widget_ops = array( 'classname' => 'wpinstagram', 'description' => __('Displays latest 20 instagrams.', 'wpinstagram') );
		$control_ops = array( 'width' => 300, 'height' => 350, 'id_base' => 'wpinstagram-widget' );
		$this->WP_Widget( 'wpinstagram-widget', __('Instagram', 'wpinstagram'), $widget_ops, $control_ops );
	}

	function widget( $args, $instance ) {
		extract( $args );

		$title = apply_filters('widget_title', $instance['title']);
		$id = $instance['id'];
		if($id){
			$images = wp_cache_get('instagrams', 'wpinstagram_cache');
			if(false == $images) {
				$images = $this->instagram_get_latest($instance);
				wp_cache_set('instagrams', $images, 'wpinstagram_cache', 3600);
			}
			if(!empty($images)){
				echo $before_widget;
				if($title){
					echo $before_title.$title.$after_title;
				}
				echo '<div class="wpinstagram">';
				foreach($images as $image){
					echo '<a href="'.$image['image_large'].'" title="'.$image['title'].'" rel="wpinstagram">'
						.'<img src="'.$image["image_small"].'" alt="'.$image['title'].'" width="150" height="150" />'
						.'</a>';
				}
				echo '</div>';
				echo $after_widget;
			}
		}
	}
	
	function instagram_get_pk($url){
		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt($ch, CURLOPT_HEADER, 0);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt($ch, CURLOPT_USERAGENT, "Instagram 1.12.1 (iPhone; iPhone OS 4.2.1; lv_LV)");
		$data = curl_exec($ch);
		$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
		if($httpcode >= 200 && $httpcode < 400){
		    $pattern = "/\/profiles\/profile_([0-9]+)_/i";
		    preg_match($pattern, $data, $matches);
		    if(isset($matches[1]) && intval($matches[1])){
		    	return $matches[1];
		    } else return new WP_Error('couldnotgetid', __("Sorry, couldn't get Your instagram ID! Try another instagram url!"));
		} else return new WP_Error('couldnotgetid', __("Sorry, couldn't access given url! Try another instagram url"));
	}
	
	
	function instagram_get_latest($instance){
		$images = array();
		if(intval($instance['id'])){
			$ch = curl_init("http://instagr.am/api/v1/feed/user/".$instance['id']);
			curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
			curl_setopt($ch, CURLOPT_HEADER, 0);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt($ch, CURLOPT_USERAGENT, "Instagram 1.12.1 (iPhone; iPhone OS 4.2.1; lv_LV)");
			$data = curl_exec($ch);
			$httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
			if($httpcode >= 200 && $httpcode < 400){
				$data = json_decode($data);
				if($data->status == "ok"){
					foreach($data->items as $item){
						$images[] = array(
							"title" => (isset($item->comments[0])?$item->comments[0]->text:""),
							"image_large" => $item->image_versions[0]->url,
							"image_middle" => $item->image_versions[1]->url,
							"image_small" => $item->image_versions[2]->url
						);
					}
				}
			}
		}
		return $images;
	}
	
	function update( $new_instance, $old_instance ) {
		$instance = $old_instance;

		$instance['title'] = strip_tags( $new_instance['title'] );
		$instance['id'] = strip_tags( $new_instance['id'] );
		if($new_instance['singleinstagram'] != ""){
			$idcheck = $this->instagram_get_pk($new_instance['singleinstagram']);
			if(is_wp_error($idcheck)){
				echo $idcheck->get_error_message();
				return $old_instance;
			} else {
				$instance['id'] = $idcheck;
			}
		}
		return $instance;
	}
	
	function form( $instance ) {

		$defaults = array( 'title' => __('My instagrams', 'wpinstagram'), 'id' => __('', 'wpinstagram') );
		$instance = wp_parse_args( (array) $instance, $defaults ); ?>

		<p>
			<label for="<?php echo $this->get_field_id( 'title' ); ?>"><?php _e('Title:', 'hybrid'); ?></label>
			<input id="<?php echo $this->get_field_id( 'title' ); ?>" name="<?php echo $this->get_field_name( 'title' ); ?>" value="<?php echo $instance['title']; ?>" class="widefat" />
		</p>

		<p>
			<label for="<?php echo $this->get_field_id( 'id' ); ?>"><?php _e('Instagram ID:', 'wpinstagram'); ?></label>
			<input id="<?php echo $this->get_field_id( 'id' ); ?>" name="<?php echo $this->get_field_name( 'id' ); ?>" value="<?php echo $instance['id']; ?>" class="widefat" />
		</p>
		
		<p>
			<label for="<?php echo $this->get_field_id( 'singleinstagram' ); ?>"><?php _e('To get Instagram ID, enter url of one of Your instagrams:', 'wpinstagram'); ?></label>
			<input id="<?php echo $this->get_field_id( 'singleinstagram' ); ?>" name="<?php echo $this->get_field_name( 'singleinstagram' ); ?>" value="<?php echo $instance['singleinstagram']; ?>" class="widefat" placeholder="http://instagr.am/p/../" />
		</p>
		
	<?php
	}
}
?>
