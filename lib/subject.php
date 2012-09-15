<?php



class PopcornLM_Subject {
	
	
	public function __construct(){
		add_action('init',array($this,'init'));
		
		
		//ajax queries
		add_action('wp_ajax_popcornlm-subject-list', array($this, 'ajaxSubjectList'));
		add_action('wp_ajax_popcornlm-single-subject', array($this, 'ajaxSingleSubject'));
		add_action('add_meta_boxes', array($this,'registerMetaBoxes'));
		add_action('admin_head',array($this,'subjectJQueryUI'));
		
		add_action('admin_menu',array($this,'removeSubmit'));
		
		add_filter( 'post_row_actions', array($this,'noQuickEdit'), 10, 1 );
		add_filter('get_sample_permalink_html', array($this,'perm'), '',4);
		add_filter( 'post_updated_messages', array($this,'updated_messages') );
		
		
		//image size for subject thumbs.
		add_image_size('popcornlm-subject-thumb',200,200);
		
		
		add_filter( 'screen_layout_columns', array($this,'so_screen_layout_columns') );
		add_filter( 'get_user_option_screen_layout_popcornlm_subjects', array($this,'so_screen_layout_post') );
		
	}
	
	
	
	public function init(){
		$this->registerPostType();
	
	}
	
	public function noQuickEdit($actions){
		if( get_post_type() === 'popcornlm_subjects' )
		        unset( $actions['inline hide-if-no-js'] );
		        
		    return $actions;
	}

	public function ajaxSubjectList(){
		
		$args = array(
					'post_type'=>'popcornlm_subjects',
					'posts_per_page'=>-1,
					'orderby'=>'title',
					'order'=>'ASC'
				);
				$loop = new WP_Query($args);
				while ($loop->have_posts()) : $loop->the_post();
				 echo '<div class="subjectAjax">'.get_the_title().'</div>';
				
				echo "\n";
				
				endwhile;
	
		die();
	}
	
	public function ajaxSingleSubject(){
		
		$subject = get_page_by_title($_GET['subject'],ARRAY_A,'popcornlm_subjects');
	
		echo json_encode(array('data'=>$subject['ID']));
		
		die();
	}
	
	
	//sets up the fields needed
	public function registerMetaBoxes(){
		add_meta_box(
				'popcornLM_Subjects_info', 
				'Person/Topic Info', 
				array($this,'showPopcornSubjectBox'), 
				'popcornLM_Subjects', 
				'normal', 
				'high'); 
				
		
	}
	
	public function so_screen_layout_columns($columns ) {
	    $columns['post'] = 1;
	    return $columns;
	}
	

	public function so_screen_layout_post() {
	    return 1;
	}
	
	
	public function subjectJQueryUI(){
		wp_enqueue_script('jquery');
		wp_enqueue_script('image-upload', POPCORNLM_PATH.'js/image-upload.js');
		
		
	}
	
	
	public function showPopcornSubjectBox(){
			// Use nonce for verification
			echo '<input type="hidden" name="custom_meta_box_nonce" value="'.wp_create_nonce(POPCORNLM_BASENAME).'" />';
			$subjectMeta = get_post_custom();
		?>
		<h2>Defining a person/topic:</h2>
		<p>Name the person/topic in the title bar above, then fill out optional info below.<br />Choose an image (if you want one) that is as close to square as possible, e.g. 200X200.</p><br />
		
		<label for="subjectSubhead">Subheading (e.g. person's position): </label>
		
		<input type="text" name="subjectSubhead" id="subjectSubhead" value="<?php echo $subjectMeta['subjectSubhead'][0];?>" /><br /><br />
		
		<label for="subjectThumb">Subject Image:<br /><small>Click the "Choose Image" button and upload the desired image. Once it is finished, scroll down and click "Insert into Post."</small></label>
		<div id="subjectThumbContainer" style="width: 500px; margin-left: 100px;">
		<div id="subjectThumbPreview" style="width: 150px;  padding: 5px; margin-left: 10px;
		margin-top: 10px; border: dotted 1px #666;">
	
		<div id="subjectPreview" style="
		<?php
		
		if(empty($subjectMeta['subjectThumb'])){
			echo 'display: none;';
		}
		
		?>
		"><img src="<?php
		if(!empty($subjectMeta['subjectThumb'])){
		 $image = wp_get_attachment_image_src($subjectMeta['subjectThumb'][0], 'popcornlm-subject-thumb'); $image = $image[0]; 
		echo $image;
		}
		?>" class="subject_preview_image" style=" display: block; margin: 0 auto; max-width: 150px; max-height: 150px; margin: 0 auto;" alt="" /></div>
			
	<span id="subjectUpload" style="
	<?php
	if(!empty($subjectMeta['subjectThumb'])){
		echo 'display:none;';
	}
	?>
	">	<input class="subject_upload_image_button button" type="button" value="Choose Image" style="margin-left: 27px; margin-top: 50px;"/> <br /><small><p style="text-align: center; color: red;">Note: Must be new upload or uploaded in this form before.</p></small></span>
		</div>
		<input name="subjectThumb" type="hidden" class="subject_upload_image" value="<?php echo $subjectMeta['subjectThumb'][0]; ?>" />
	
		
		 
		
		<small> <p style="margin-left: 53px;"><a href="#" class="subject_clear_image_button">Remove Image</a><br /></p></small></div>
	<br/>
		<h1 style="margin-bottom: 5px;">Background Info: </h1><br />
	<?php
	wp_editor($subjectMeta['popcornsubjectinfo'][0],'popcornsubjectinfo');
	?>
		
		
		<br /><br />
			<input name="original_publish" type="hidden" id="original_publish" value="Publish" />
				<input type="submit" name="publish" id="publish" class="button-primary" value="Create/Update Subject" tabindex="5" accesskey="p"  />
		<?php
		
	}
	
	
	
	//registers the post type
	public function registerPostType(){
	
		  $labels = array(
		    'name' => _x('Popcorn Analysis - People/Topics', 'post type general name'),
		    'singular_name' => _x('Person/Topic', 'post type singular name'),
		    'add_new' => _x('Add New Person or Topic', 'book'),
		    'add_new_item' => __('Add New Person or Topic'),
		    'edit_item' => __('Edit Person/Topic'),
		    'new_item' => __('New Person/Topic'),
		    'all_items' => __('People/Topics'),
		    'view_item' => __('View Person/Topic'),
		    'search_items' => __('Search People/Topics'),
		    'not_found' =>  __('No people/topics found'),
		    'not_found_in_trash' => __('No people/topics found in Trash'), 
		    'parent_item_colon' => '',
		    'menu_name' => __('People/Topics')
		  );
		
		  $args = array(
		    'labels' => $labels,
		    'public' => false,
		    'publicly_queryable' => false,
		    'show_ui' => true, 
		    'show_in_menu' => 'edit.php?post_type=popcornlm', 
		    'query_var' => true,
		    'rewrite' => true,
		    'capability_type' => 'post',
		    'has_archive' => true, 
		    'hierarchical' => false,
		    'menu_position' => null,
		    'supports' => array( 'title')
		  ); 
		  register_post_type('popcornLM_Subjects',$args);
		
		
		
		//manually add a submenu page
	//	add_submenu_page('edit.php?post_type=popcornlm');
	}
	
	
		public function updated_messages( $messages ) {
		  global $post, $post_ID;

		  $messages['popcornlm_subjects'] = array(
		    0 => '', // Unused. Messages start at index 1.
		    1 => 'Person/Topic updated.',
		    2 => 'Custom field updated.',
		    3 => 'Custom field deleted.',
		    4 => 'Person/Topic updated.',
		    /* translators: %s: date and time of the revision */
		    5 => isset($_GET['revision']) ? sprintf( __('Person/Topic restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
		    6 => 'Person/Topic created.',
		    7 => 'Person/Topic saved.',
		    8 => 'Person/Topic created.',
		    9 => 'Person/Topic scheduled.',
		    10 => 'Person/Topic draft updated.'
		  );

		  return $messages;
		}
	
	
		public function perm($return, $id, $new_title, $new_slug){
		        global $post;
		        if($post->post_type == 'testimonials')
		        {
		            $ret2 = preg_replace('/<span id="edit-slug-buttons">.*<\/span>|<span id=\'view-post-btn\'>.*<\/span>/i', '', $return);
		        }

		        return $ret2;
		}
	
		public function removeSubmit(){
			remove_meta_box( 'submitdiv', 'popcornlm_subjects', 'side' );
		}
	
	
}


?>