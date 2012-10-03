<?php
//This class allows us to populate sources in the WP database so we can reference them in multiple videos that may have overlapping issues. It also provides us with stronger ability in the future to "vet" a source, and analyze how often it is used by the site and in what contexts. Over time, sources can accumulate in the WP database so they can be referenced in the future. Which is why we will also need to create categories and taxonomies so resources can be searched in the future.




class PopcornLM_Source {
	
	public function __construct(){
		add_action('init',array($this,'init'));
		add_action( 'init', array($this,'popcornLMSourceTaxonomies'));
		
		
		
		
		
	
		
		add_action('add_meta_boxes', array($this,'registerMetaBoxes'));
		
		add_action('admin_menu',array($this,'removeSubmit'));
		
		
		
		
		
		
		add_filter( 'post_row_actions', array($this,'noQuickEdit'), 10, 1 );
		add_filter('get_sample_permalink_html', array($this,'perm'), '',4);
		add_filter( 'post_updated_messages', array($this,'updated_messages') );
		
	
		
	
	
		
	}
	
	
	
	
	
	
	
	public function showPopcornSourceBox(){
		// Use nonce for verification
		echo '<input type="hidden" name="custom_meta_box_nonce" value="'.wp_create_nonce(POPCORNLM_BASENAME).'" />';
		$sourceMeta = get_post_custom();

		?>
		<h2>Setting up the source:</h2>
		<p>First, add in the title above, URL if it exists, and abstract/brief description of the source. Next, choose from the types of sources in the box "Source Type," Last, enter comma-separated tags in the "Source Tags" box that are related to this source, e.g. global warming, climate change, clowns, etc.</p>
		
		<h4>Step 1: Is there a URL? If so, enter it here (enter the title above):</h4>
			<input type="text" name="sourceUrl" id="sourceUrl" value="<?php echo $sourceMeta['sourceUrl'][0];?>" size="32" /><br /><br />
			<h4>Step 2: What kind of source is it? Choose from the "Source Type" box on this page. If yours type isn't there, add it. In most cases you should only check one box. Add any relevant tags to "Source Tags," e.g. global warming, climate change, fair use.</h4>
			
			<h4>Step 3: Provide an abstract or brief description:</h4>
			
		<?php
		if($sourceMeta['popcornsourceabstract'][0]){
			$content = $sourceMeta['popcornsourceabstract'][0];
		}else{
			$content = '';
		}
		$args = array(
			'textarea_rows'=>5
		//	'quicktags'=>false
			
		);
		
		wp_editor($content,'popcornsourceabstract',$args);
		
		?>
		<br /><br /><input name="original_publish" type="hidden" id="original_publish" value="Publish" />
				<input type="submit" name="publish" id="publish" class="button-primary" value="Create/Update Source" tabindex="5" accesskey="p"  />
		<?php
		
	}
	
	
	
	
	//create two taxonomies, 
	public function popcornLMSourceTaxonomies() 
	{
	  // Add new taxonomy, make it hierarchical (like categories)
	  $labels = array(
	    'name' => _x( 'Source Types', 'taxonomy general name' ),
	    'singular_name' => _x( 'Source Type', 'taxonomy singular name' ),
	    'search_items' =>  __( 'Search Source Types' ),
	    'all_items' => __( 'All Source Types' ),
	    'parent_item' => __( 'Parent Source Type' ),
	    'parent_item_colon' => __( 'Parent Source Type:' ),
	    'edit_item' => __( 'Edit Source Type' ), 
	    'update_item' => __( 'Update Source Type' ),
	    'add_new_item' => __( 'Add New Source Type' ),
	    'new_item_name' => __( 'New Source Type Name' ),
	    'menu_name' => __( 'Source Types' ),
	  ); 	

	  register_taxonomy('source_types',array('popcornlm_sources'), array(
	    'hierarchical' => true,
	    'labels' => $labels,
	    'show_ui' => true,
	    'query_var' => true,
	    'rewrite' => array( 'slug' => 'source_types' ),
	  ));

	  // Add new taxonomy, NOT hierarchical (like tags)
	  $labels = array(
	    'name' => _x( 'Source Tags', 'taxonomy general name' ),
	    'singular_name' => _x( 'Source Tag', 'taxonomy singular name' ),
	    'search_items' =>  __( 'Search Source Tags' ),
	    'popular_items' => __( 'Popular Source Tags' ),
	    'all_items' => __( 'All Source Tags' ),
	    'parent_item' => null,
	    'parent_item_colon' => null,
	    'edit_item' => __( 'Edit Source Tag' ), 
	    'update_item' => __( 'Update Source Tag' ),
	    'add_new_item' => __( 'Add New Source Tag' ),
	    'new_item_name' => __( 'New Source Tag Name' ),
	    'separate_items_with_commas' => __( 'Separate source tags with commas' ),
	    'add_or_remove_items' => __( 'Add or remove source tags' ),
	    'choose_from_most_used' => __( 'Choose from the most used source tags' ),
	    'menu_name' => __( 'Source Tags' ),
	  ); 

	  register_taxonomy('source_tags','popcornlm_sources',array(
	    'hierarchical' => false,
	    'labels' => $labels,
	    'show_ui' => true,
	    'update_count_callback' => '_update_post_term_count',
	    'query_var' => true,
	    'rewrite' => array( 'slug' => 'source_tags' ),
	  ));
	}
	
	
	
	
	
	
	
	public function init(){
		$this->registerPostType();
	
	}
	
	public function noQuickEdit($actions){
		if( get_post_type() === 'popcornlm_sources' )
		        unset( $actions['inline hide-if-no-js'] );
		        
		    return $actions;
	}

	
	
	
	//sets up the fields needed
	public function registerMetaBoxes(){
		add_meta_box(
				'popcornLM_source_info', 
				'Source Info', 
				array($this,'showPopcornSourceBox'), 
				'popcornlm_sources', 
				'normal', 
				'high'); 
				
		
	}
	
	
	//registers the post type
	public function registerPostType(){
	
		  $labels = array(
		    'name' => _x('Popcorn Analysis - Sources', 'post type general name'),
		    'singular_name' => _x('Source', 'post type singular name'),
		    'add_new' => _x('Add New Source', 'book'),
		    'add_new_item' => __('Add New Source'),
		    'edit_item' => __('Edit Source'),
		    'new_item' => __('New Source'),
		    'all_items' => __('Sources'),
		    'view_item' => __('View Sources'),
		    'search_items' => __('Search Sources'),
		    'not_found' =>  __('No sources found'),
		    'not_found_in_trash' => __('No sources found in Trash'), 
		    'parent_item_colon' => '',
		    'menu_name' => __('Sources')
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
		  register_post_type('popcornLM_Sources',$args);
		
		
		
		
	}
	
	
		public function updated_messages( $messages ) {
		  global $post, $post_ID;

		  $messages['popcornlm_source'] = array(
		    0 => '', // Unused. Messages start at index 1.
		    1 => 'Source updated.',
		    2 => 'Custom field updated.',
		    3 => 'Custom field deleted.',
		    4 => 'Source updated.',
		    /* translators: %s: date and time of the revision */
		    5 => isset($_GET['revision']) ? sprintf( __('Label Template restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
		    6 => 'Source created/updated.',
		    7 => 'Source saved.',
		    8 => 'Source created.',
		    9 => 'Source scheduled.',
		    10 => 'Source draft updated.'
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
			remove_meta_box( 'submitdiv', 'popcornlm_sources', 'side' );
		}
		
	
	public function so_screen_layout_columns($columns ) {
	    $columns['post'] = 1;
	    return $columns;
	}
	

	public function so_screen_layout_post() {
	    return 1;
	}
	
	

}
?>