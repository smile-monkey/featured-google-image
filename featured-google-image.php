<?php
/*
Plugin Name: Featured Google Image
Plugin URI: https://
Description: Automatically display missing featured image in posts and pages to Image Google Searched. Supports YouTube, Vimeo, Facebook, Vine, Justin.tv, Twitch, Dailymotion, Metacafe, VK, Blip, Google Drive, Funny or Die, CollegeHumor, MPORA, Wistia, Youku, and Rutube.
Author:
Author URI: http://
Version: 1.0
License: 
Text Domain: featured-google-image
Domain Path: /languages/
*/
/**
* 
*/
if ( ! defined( 'ABSPATH' ) ) exit;

if (!class_exists(Featured_Google_Image)){
	class Featured_Google_Image {

		function __construct(){
			add_action('admin_init', array(&$this, 'featured_image_init'));
			// Add options page to menu
			add_action( 'admin_menu', array( &$this, 'add_option_menu' ) );
			// Add meta tag
			add_action( 'add_meta_boxes',array( &$this, 'add_google_image_meta' ));
			// Ajax define.
			add_action( 'wp_ajax_get_featured_google_image', array(&$this,'get_featured_google_image'));
			add_action( 'wp_ajax_set_featured_google_image', array(&$this,'set_featured_google_image'));
			add_action( 'wp_ajax_bulk_featured_google_image', array( &$this, 'bulk_featured_google_image'));
		}

		function add_option_menu() {
			add_options_page(
				__( 'Bulk Featured Image', 'featured_google_iamge' ),
				__( 'Bulk Featured Image', 'featured_google_iamge' ),
				'manage_options',
				'featured_google_image',
				array( &$this, 'featured_image_option' )
			);
		}

		function featured_image_init(){
			include_once (__DIR__ . '/simple_html_dom.php');
			wp_enqueue_script( 'scroll-library-js', plugins_url( '/js/infinitescroll.js' , __FILE__ ), array( 'jquery' ), "1.0" );
			wp_enqueue_script( 'featuredgoogleimage-js', plugins_url( '/js/featuredgoogleimage.min.js' , __FILE__ ), array( 'jquery' ),"1.0");
			wp_enqueue_style( 'featuredgoogleimage-css', plugins_url('/css/featuredgoogleimage.min.css', __FILE__), false, "all" );			
		}
		// Bulk Featured_Google_Image Page
		function featured_image_option() {
			?><div class="wrap">
				<h2><?php _e( 'Bulk Featured_Google_Image', 'featured_google_iamge' ); ?></h2>
				<div>
					<div style="float: left;margin: 10px;">
						<button type="button" id="featured_image_btn">Bulk Featured Image</button>
					</div>
					<div style="float: left;">
						<img id="progress_bar" src="<?php echo plugins_url( 'images/loading.gif', __FILE__ );?>"/>
					</div>
				<div>
				<div id="google_image_list"></div>
			</div><?php
		}

		function add_google_image_meta(){
			add_meta_box( 'google_image_meta', 'Featured Google Image', array( &$this, 'google_image_meta_box' ), 'post', 'side', 'low' );
		}

		function google_image_meta_box(){
			global $post;
			echo '<p><a href="#" id="set_google_image" onclick="show_google_iamge_form(\'' . $post->ID . '\' );">' . __( 'Set featured google image', 'set_google_image' ) . '</a></p>';
			// Featured Google Image Modal Dialog
			$googlesearch_modal = "
				<div class='main_modal'>
					<button type='button' class='media-modal-close'><span class='media-modal-icon'></span></button>
					<div class='content_modal'>
						<div class='title_modal'>
							<h1>Featured Google Image</h1>
						</div>
						<div class='modal_toolbar'>
							<div class='top_toolbar'>
								<div style='float:left;'>
									<img src='".plugins_url( 'images/google.png', __FILE__ )."'/>
								</div>
								<div style='float:left;margin:0 20px;'>
									<input type='search' id='search_input' placeholder='Insert image title'/>
								</div>
								<div style='float:left;'>
									<img id='img_loading' src='".plugins_url( 'images/loading.gif', __FILE__ )."'/>
								</div>
							</div>
						</div>
						<div id='image_content'></div>
						<div class='bottom_modal'>
							<div class='bottom_toolbar'>
								<button type='button' id='set_btn' class='set_image_btn'>Set google image</button>
							</div>
						</div>
					</div>
				</div>

			";
			echo $googlesearch_modal;
		}

		// Display Google Search Result
		function get_featured_google_image(){
			$post_title = $_POST['post_title'];
			$page_num = $_POST['page_num']-1;

			// 4 * 5 images
			$image_list = "<ol><li>";
			$img_urls = $this->get_google_images($post_title,$page_num);
			if (count($img_urls) > 0){	
				foreach ($img_urls as $key => $img_url) {
					if (($key % 4) == 0) $image_list .="</li><li>";
					if ($img_url['src'])
						$image_list .= "<img src='".$img_url['src']."' class='img_size' tabindex='0' alt='".$img_url['href']."'></img>";
				}
				$image_list .= "</li></ol>";
			}else{
				$image_list = "<h1 align='center'>No featured google image for title '".$post_title."'.</h1>";
			}
			echo $image_list;
			die();
		}

		// Get Featured Image from Google using curl command
		function get_google_images($post_title,$start=0){
			$image_urls = array();

	        $params = [
	            'q' => $post_title,
	            'source' => 'lnms',
	            'tbm' => 'isch',
	            'sa' => 'X',
	            'ved' => '0ahUKEwia_p_lupDVAhWFjZQKHY-jD3IQ_AUIDCgD',
	            'biw'=>'1680',
	            'bih'=>'944',
	            // 'dpr'=>'1',
	            'start' => $start*20
	        ];
			$curl_url = 'https://www.google.com/search?'.http_build_query($params);

			$html = $this->get_html_dom($curl_url);
	        $href_arr = $html->find('.images_table a');
	        $img_tags = $html->find('.images_table img');
	        $img_td = $html->find('.images_table td');
	        if (count($img_tags)>0){
	        	foreach ($img_tags as $key=>$img_tag) {
	        		$img_temp = $img_td[$key]->nodes[count($img_td[$key]->nodes)-1]->_[4];
	        		$img_temp = explode('-', $img_temp);
	        		$img_size = explode('&times;', $img_temp[0]);
	        		$img_width = trim($img_size[0]);
	        		$img_height = trim($img_size[1]);
	        		$image_urls[$key]['src'] = $img_tag->attr['src'];
	        		$image_urls[$key]['href'] = $img_width.'*'.$img_height.'*'.$href_arr[$key]->attr['href'].'*'.$img_tag->attr['src'];
	        	}
	        }
			return $image_urls;
		}

		function get_html_dom($curl_url){
	        $ch = curl_init();
	        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, FALSE);
	        curl_setopt($ch, CURLOPT_HEADER, false);
	        // curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.36');
	        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
	        curl_setopt($ch, CURLOPT_URL, $curl_url);
	        curl_setopt($ch, CURLOPT_REFERER, $curl_url);
	        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
	        $html = curl_exec($ch);
	        curl_close($ch);
	        $html = \simplehtmldom_1_5\str_get_html($html);
	        // echo $html;exit;
	        return $html;
		}

		function set_featured_google_image(){
			$post_id = $_POST['post_id'];
			$image_data = $_POST['image_url'];

			$img_real_url = $this->get_img_real_url($image_data);
			if ($img_real_url){
				$attach_id = $this->save_to_media_library($img_real_url,$post_id);
				if ($attach_id >0)
					echo _wp_post_thumbnail_html($attach_id,$post_id);
			}
			die();		
		}

		function get_img_real_url($image_data) {
			$img_real_url = '';
			$image_data = explode('*', $image_data);
			$img_width = trim($image_data[0]);
			$img_height = trim($image_data[1]);
			$img_url = trim($image_data[2]);
			$curl_url = 'https://www.google.com'.$img_url;
			$site_html = $this->get_html_dom($curl_url);
			$img_tags = $site_html->find('img');
			if (sizeof($img_tags)>0){
				foreach ($img_tags as $key => $img_tag) {
					$img_attr = $img_tag->attr;
					if (($img_attr['width']==$img_width)&&($img_attr['height']==$img_height)) {
						$img_real_url = $img_attr['src'];break;
					}
				}
			}
			if (!$img_real_url)
				$img_real_url = trim($image_data[3]);
			return $img_real_url;			
		}
		/**
		 * Bulk missing featured images from google
		 */
		function bulk_featured_google_image(){
			global $wpdb;
			set_time_limit(3600);
			$prefix = $wpdb->prefix;
			$post_data = $wpdb->get_results("SELECT m.post_id, m.meta_value, p.post_title FROM ".$prefix."postmeta AS m
								JOIN ".$prefix."posts AS p ON m.post_id=p.ID
								WHERE p.post_type = 'post' AND m.meta_key='_thumbnail_id' AND p.post_title!=''");
			$upload_dir = wp_upload_dir();
			$upload_path = trailingslashit($upload_dir['baseurl']);
			$image_tr_html = "";
			if (sizeof($post_data)>0){
				$num = 0;
				foreach ($post_data as $p_obj) {
					$img_path = wp_get_attachment_image_url($p_obj->meta_value);
					$old_path = $upload_path.$img_path;
					if (!file_exists($old_path)){
						$wpdb->query("DELETE FROM ".$prefix."postmeta WHERE post_id = '".$p_obj->meta_value.'"');
						$wpdb->query("DELETE FROM ".$prefix."post WHERE ID = '".$p_obj->meta_value.'"');						
						$img_urls = $this->get_google_images($p_obj->post_title,0);
						$image_data =$img_urls[0]['href'];
						$image_url = $this->get_img_real_url($image_data);
						if ($image_url){
							$attach_id = $this->save_to_media_library($image_url,$p_obj->post_id);
							update_post_meta($p_obj->post_parent,'_thumbnail_id',$attach_id);
							$new_path = wp_get_attachment_image_url($attach_id);
							$image_tr_html .="<tr><td>".($num+1)."</td><td>".$p_obj->post_title."</td><td>".$old_path."</td><td>".$new_path."</tr>";
							$num++;
						}
					}
				}
				$result_html = '<p style="font-size: 18px;font-weight: bold;text-align: center;">Result:'.$num.'/'.count($post_data).'</p>
						<table id="image_list" style="text-align: center;font-size: 14px;" border="1px">
							<tr><td style="width:60px;">No</td><td>Post Title</td><td>Missing Image Url</td><td>Bulk Image Url</td></tr>'.$image_tr_html.'</table>';
				echo json_encode($result_html);
			}
			die();			
		}

		/**
		 * Saves a remote image to the media library
		 * @param  string $image_url URL of the image to save
		 * @param  int    $post_id   ID of the post to attach image to
		 * @return int               ID of the attachment
		 */
		public static function save_to_media_library( $image_url, $post_id ) {
			global $wpdb;
			$image_extension = '.jpg';
			// Construct a file name with extension
			$new_filename = self::construct_filename( $post_id ) . $image_extension;

			$prefix = $wpdb->prefix;
			$row = $wpdb->get_var($wpdb->prepare("SELECT post_id,meta_value FROM ".$prefix."postmeta WHERE INSTR (meta_value,'".$new_filename."')"));
			$attach_id = $row->post_id;
			$upload_dir = wp_upload_dir();
			$img_path = trailingslashit($upload_dir['basedir']).$row->meta_value;
			// If image file exist
			if ($attach_id){
				if (file_exists($img_path)){
					return $attach_id;
				}else {
					$wpdb->query("DELETE FROM ".$prefix."postmeta WHERE post_id = '".$attach_id.'"');
					$wpdb->query("DELETE FROM ".$prefix."post WHERE ID = '".$attach_id.'"');
				}
			}

			$image_contents = file_get_contents($image_url);
			// Save the image bits using the new filename
			do_action( 'featured_google_image/pre_upload_bits', $image_contents );
			$upload = wp_upload_bits( $new_filename, null, $image_contents );
			do_action( 'featured_google_image/after_upload_bits', $upload );
			// Stop for any errors while saving the data or else continue adding the image to the media library
			if ( $upload['error'] ) {
				$error = new WP_Error( 'thumbnail_upload', __( 'Error uploading image data:', 'video-thumbnails' ) . ' ' . $upload['error'] );
				return $error;
			} else {

				do_action( 'featured_google_image/image_downloaded', $upload['file'] );

				$wp_filetype = wp_check_filetype( basename( $upload['file'] ), null );

				$upload = apply_filters( 'wp_handle_upload', array(
					'file' => $upload['file'],
					'url'  => $upload['url'],
					'type' => $wp_filetype['type']
				), 'sideload' );

				// Contstruct the attachment array
				$attachment = array(
					'post_mime_type'	=> $upload['type'],
					'post_title'		=> get_the_title( $post_id ),
					'post_content'		=> '',
					'post_status'		=> 'inherit'
				);
				// Insert the attachment
				$attach_id = wp_insert_attachment( $attachment, $upload['file'], $post_id );
				// you must first include the image.php file
				// for the function wp_generate_attachment_metadata() to work
				require_once( ABSPATH . 'wp-admin/includes/image.php' );
				do_action( 'featured_google_image/pre_generate_attachment_metadata', $attach_id, $upload['file'] );
				$attach_data = wp_generate_attachment_metadata( $attach_id, $upload['file'] );
				do_action( 'featured_google_image/after_generate_attachment_metadata', $attach_id, $upload['file'] );
				wp_update_attachment_metadata( $attach_id, $attach_data );

				// Add field to mark image as a video thumbnail
				update_post_meta( $attach_id, 'video_thumbnail', '1' );
			}
			return $attach_id;

		}

		/**
		 * Creates a file name for use when saving an image to the media library.
		 * It will either use a sanitized version of the title or the post ID.
		 * @param  int    $post_id The ID of the post to create the filename for
		 * @return string          A filename (without the extension)
		 */
		static function construct_filename( $post_id ) {
			$filename = get_the_title( $post_id );
			$filename = sanitize_title( $filename, $post_id );
			$filename = urldecode( $filename );
			$filename = preg_replace( '/[^a-zA-Z0-9\-]/', '', $filename );
			$filename = substr( $filename, 0, 32 );
			$filename = trim( $filename, '-' );
			if ( $filename == '' ) $filename = (string) $post_id;
			return $filename;
		}
	}
}
$featuredGoolgeImage = new Featured_Google_Image();
?>