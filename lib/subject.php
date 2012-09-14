<?php



class PopcornLM_Subject {
	
	
	public function __construct(){
		add_action('init',array($this,'init'));
		
		
		//ajax queries
		add_action('wp_ajax_popcornlm-subject-list', array($this, 'ajaxSubjectList'));
		add_action('wp_ajax_popcornlm-single-subject', array($this, 'ajaxSingleSubject'));
		add_action('add_meta_boxes', array($this,'registerMetaBoxes'));
		
	//	add_action('admin_menu',array($this,'removeSubmit'));
		
		add_filter( 'post_row_actions', array($this,'noQuickEdit'), 10, 1 );
		add_filter('get_sample_permalink_html', array($this,'perm'), '',4);
		add_filter( 'post_updated_messages', array($this,'updated_messages') );
		
		
		//image size for subject thumbs.
		add_image_size('popcornlm-subject-thumb',200,200);
		
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
	
	public function showPopcornSubjectBox(){
		
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