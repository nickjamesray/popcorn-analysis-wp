<?php



class PopcornLM_Label {
	
	
	public function __construct(){
		add_action('init',array($this,'init'));
		
		add_action('admin_head',array($this,'labelJQueryUI'));
		
		add_action('add_meta_boxes', array($this,'registerMetaBoxes'));
		
		add_action('admin_menu',array($this,'removeSubmit'));
		
		add_filter( 'post_row_actions', array($this,'noQuickEdit'), 10, 1 );
		add_filter('get_sample_permalink_html', array($this,'perm'), '',4);
		add_filter( 'post_updated_messages', array($this,'updated_messages') );
		
		add_filter( 'screen_layout_columns', array($this,'so_screen_layout_columns') );
		add_filter( 'get_user_option_screen_layout_popcornlm_labels', array($this,'so_screen_layout_post') );
		
	
	
		
	}
	
	public function so_screen_layout_columns($columns ) {
	    $columns['post'] = 1;
	    return $columns;
	}
	

	public function so_screen_layout_post() {
	    return 1;
	}
	
	
	//returns default config.
	public function getDefaultLabel(){
		$array = array(
			'name'=>'Default True/Uncertain/False',
			'colors'=>array(
				'True'=>'33BB00',
				'False'=>'CC1100',
				'Uncertain'=>'444444'
			)
		);
	
		return $array;
	}
	
	
	public function init(){
		$this->registerPostType();
	
	}
	
	public function noQuickEdit($actions){
		if( get_post_type() === 'popcornlm_labels' )
		        unset( $actions['inline hide-if-no-js'] );
		        
		    return $actions;
	}

	
	
	
	//sets up the fields needed
	public function registerMetaBoxes(){
		add_meta_box(
				'popcornLM_label_info', 
				'Label Template Info', 
				array($this,'showPopcornLabelBox'), 
				'popcornlm_labels', 
				'normal', 
				'high'); 
				
		
	}
	
	public function labelJQueryUI(){
		wp_enqueue_script('jquery');
		
?>
		<script type="text/javascript" src="<?php echo POPCORNLM_PATH.'js/jscolor/jscolor.js';?>"></script>
		
		
		<script type="text/javascript">
		jQuery(document).ready(function(){
			
			
		jQuery('.templateAdd').live('click',function(e){
	
		now = Math.round((new Date()).getTime() / 1);
		
			
		var newListElem = '<div class="singleTemplate" id="colContainer-'+now+'"><input type="text" name="label['+now+']" /></div>';

		jQuery('#templateInfo').append(newListElem);
		
		var input = document.createElement('INPUT');
				input.style.width = '4.5em';
				
				input.setAttribute("id","colField-"+now);
				input.setAttribute("type","text");
				

				// bind jscolor
				var col = new jscolor.color(input);
				

				document.getElementById('colContainer-'+now).appendChild(input);
		
			jQuery("#colField-"+now).attr('name','col['+now+']');
		
		var numCol = jQuery('.singleTemplate').length;
		
		if(numCol>=8){
			jQuery('.templateAdd').hide();
		}
		
		e.preventDefault();
		});
		
		jQuery('.templateRemove').live('click',function(e){
			
			
			if(numCol<=8){
				jQuery('.templateAdd').show();
			}
			e.preventDefault();
		});
	
		
		});
		
		
		</script>
		<?php
		
	}
	
	
	public function showPopcornLabelBox(){
		
		// Use nonce for verification
		echo '<input type="hidden" name="custom_meta_box_nonce" value="'.wp_create_nonce(POPCORNLM_BASENAME).'" />';
			
		
		?>
		<h2>Setting up the template:</h2>
		<p>For each label (e.g. True,False,Funny,etc.) insert the label name, and choose a color. This color will help identify the label in the player.</p>
		
		<div id="templateInfo">
		<?php
			$labelMeta = get_post_custom();
			if(isset($labelMeta['labelVals'])){
				
			
				if(!empty($labelMeta['labelVals'][0])&&$labelMeta['labelVals'][0]!='[]'){
						$data = json_decode($labelMeta['labelVals'][0]);
						
						foreach($data as $id=>$info){
					
						?>
						<div class="singleTemplate"><input type="text" name="label[<?php echo $id; ?>]" value="<?php echo $info->label; ?>" />
					<input type="text" class="color" name="col[<?php echo $id; ?>]" value="<?php echo $info->col; ?>" size="3" />
					</div>
						
						<?php
						}
					
				}else{
					echo 'hey';
				}
				
				
			}else{
				?>
				<div class="singleTemplate"><input type="text" name="label[0]" />
				<input type="text" class="color" name="col[0]" id="testColor" value="" size="3" /> 
				</div>
				<?php
			}
		
		?>
		
		</div>
		<br />
		<a class="templateAdd button" href="#">Add Label</a><br />
		<br /><br /><input class="button-primary" type="submit" name="save" value="Configure" id="initOptionsSubmit">
		
		<?php
	
		
		
	
		
	}
	
	
	

	
	//registers the post type
	public function registerPostType(){
	
		  $labels = array(
		    'name' => _x('Popcorn Analysis - Label Templates', 'post type general name'),
		    'singular_name' => _x('Label Template', 'post type singular name'),
		    'add_new' => _x('Add New Label Template', 'book'),
		    'add_new_item' => __('Add New Label Template'),
		    'edit_item' => __('Edit Label Template'),
		    'new_item' => __('New Label Template'),
		    'all_items' => __('Label Templates'),
		    'view_item' => __('View Label Template'),
		    'search_items' => __('Search Label Templates'),
		    'not_found' =>  __('No label templates found'),
		    'not_found_in_trash' => __('No label templates found in Trash'), 
		    'parent_item_colon' => '',
		    'menu_name' => __('Label Templates')
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
		  register_post_type('popcornLM_Labels',$args);
		
		
		
		
	}
	
	
		public function updated_messages( $messages ) {
		  global $post, $post_ID;

		  $messages['popcornlm_labels'] = array(
		    0 => '', // Unused. Messages start at index 1.
		    1 => 'Label Template updated.',
		    2 => 'Custom field updated.',
		    3 => 'Custom field deleted.',
		    4 => 'Label Template updated.',
		    /* translators: %s: date and time of the revision */
		    5 => isset($_GET['revision']) ? sprintf( __('Label Template restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
		    6 => 'Label Template created.',
		    7 => 'Label Template saved.',
		    8 => 'Label Template created.',
		    9 => 'Label Template scheduled.',
		    10 => 'Label Template draft updated.'
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
			remove_meta_box( 'submitdiv', 'popcornlm_labels', 'side' );
		}
	
	
}


?>