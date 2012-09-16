<?php
/*
Plugin Name: Popcorn Analysis
Description: Plugin integrating Popcorn.js and Wordpress to create time-based judgments, e.g. a fact-check for a political speech or debate.
Author: Nick Ray
Version: 0.8
Author URI: http://nickjamesray.com
*/


define('POPCORNLM_PATH',plugin_dir_url(__FILE__));
define('POPCORNLM_BASENAME',basename(__FILE__));

require_once(dirname(__FILE__).'/lib/subject.php');
require_once(dirname(__FILE__).'/lib/label.php');
require_once(dirname(__FILE__).'/lib/display.php');
require_once(dirname(__FILE__).'/lib/ajax.php');



//Originally called Popcorn List Maker. PopcornLM is preserved to avoid plugin conflicts.
class PopcornLM {
	
	public $class;
	
	// Field Array
	public $prefix;
	public $custom_meta_fields;
	public $subFields;
	public $optionFields = array(
		array(
			'label' => 'Number of Columns',  
		 	'desc'  => 'How many speakers/subjects are there? Up to 3 for now.',  
	    	'id'    => 'numCols',  
		    'type'  => 'select',  
		),
		array(
			'label' => 'Column Titles',  
		 	'desc'  => 'Comma separated list of titles.',  
	    	'id'    => 'colTitles',  
		    'type'  => 'text',  
		),
		array(
			'label' => 'Outcomes',  
		 	'desc'  => 'Example: True or False. Fewer is more effective.',  
	    	'id'    => 'vidOutcomes',  
		    'type'  => 'repeatable',  
		),
	);
	//const PLUGINDIR = plugin_dir_url(__FILE__); fix list
	
	public function setProperties(){
		$this->prefix = 'popcornLM_';
		$this->custom_meta_fields = array(
			array(
				'label' => 'YouTube Link',  
			 	'desc'  => 'Paste in the URL for the YouTube video. Example: http://youtube.com/watch?v=SOMEID',  
		    	'id'    => $this->prefix.'youtube',  
			    'type'  => 'videoLink',  
			),
			array(
				'label' => 'Popcorn Link Block',
				'desc' => 'Compiled resources',
				'id' => $this->prefix.'linkBlock',
				'type' => 'contentArray',
				'key' => 'timeOfPost'
			),
			array(
				'label' => 'Popcorn Options Block',
				'desc' => 'Compiled options',
				'id' => $this->prefix.'optionsBlock',
				'type' => 'contentArray'
				
			)
		);
		return $this->custom_meta_fields;
	}
	
	public function setClasses(){
		//last we set up this so we can put it under the popcorn list video.
		$popcornLM_Subject = new PopcornLM_Subject();
		$popcornLM_Label = new PopcornLM_Label();
		$popcornLM_Ajax = new PopcornLM_Ajax();
		
		$this->class = array(
			'subjects'=>$popcornLM_Subject,
			'labels'=>$popcornLM_Label,
		);
		return $this->class;
	}
	
	public function setSubFields(){
		$this->subFields = array(
				array(
					'label' => 'Time',  
				 	'desc'  => 'Scrub to the time you want',  
			    	'id'    => 'videoTime',  
				    'type'  => 'videoTime',  
				),
				array(
					'label' => 'Topic',  
				 	'desc'  => 'Scrub to the time you want',  
			    	'id'    => 'topic',  
				    'type'  => 'text',  
				),
				array(
					'label' => 'Info',  
				 	'desc'  => 'Scrub to the time you want',  
			    	'id'    => 'popcorninfobody',  
				    'type'  => 'wsiwyg',  
				),
				
		);
		return $this->subFields;
	}
	
	
	public function __construct(){

		$this->setProperties();
		$this->setSubFields();
		//add_action('admin_menu',array($this,'adminActions'));
		add_action('init',array($this,'init'));
		add_action('add_meta_boxes', array($this,'registerMetaBoxes'));
		add_action('admin_head',array($this,'jqueryUIMenus'));
		add_action('save_post', array($this,'savePopcornMeta'));
		
		add_filter( 'post_row_actions', array($this,'noQuickEdit'), 10, 1 );
		add_filter( 'screen_layout_columns', array($this,'so_screen_layout_columns') );
		add_filter( 'get_user_option_screen_layout_popcornlm', array($this,'so_screen_layout_post') );
		add_filter('add_meta_boxes_popcornlm',array($this,'moveSubmit'));
		add_filter('get_sample_permalink_html', array($this,'perm'), '',4);
		add_filter( 'post_updated_messages', array($this,'updated_messages') );
		
		
			add_action('admin_notices', array($this,'my_admin_notice'));
		
		
		add_shortcode('popcornLM', array($this,'shortcode'));
	
		$this->setClasses();
	
		
	}
	
	public function shortcode($atts){
		echo $atts['id'];
	}
	
	public function noQuickEdit($actions){
		if( get_post_type() === 'popcornlm' )
		        unset( $actions['inline hide-if-no-js'] );
		        
		    return $actions;
	}
	
	public function perm($return, $id, $new_title, $new_slug){
	        global $post;
	        if($post->post_type == 'testimonials')
	        {
	            $ret2 = preg_replace('/<span id="edit-slug-buttons">.*<\/span>|<span id=\'view-post-btn\'>.*<\/span>/i', '', $return);
	        }

	        return $ret2;
	}
	
	
		//add filter to ensure the text Book, or book, is displayed when user updates a book 

		public function updated_messages( $messages ) {
		  global $post, $post_ID;

		  $messages['popcornlm'] = array(
		    0 => '', // Unused. Messages start at index 1.
		    1 => 'Video entry updated.',
		    2 => 'Custom field updated.',
		    3 => 'Custom field deleted.',
		    4 => 'Video entry updated.',
		    /* translators: %s: date and time of the revision */
		    5 => isset($_GET['revision']) ? sprintf( __('Book restored to revision from %s'), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
		    6 => 'Video entry created/updated.',
		    7 => 'Video saved.',
		    8 => 'Video entry created/updated.',
		    9 => 'Video scheduled.',
		    10 => 'Video draft updated.'
		  );

		  return $messages;
		}
		
	
	
	
	
	
		public function my_admin_notice(){
			$screen = get_current_screen();

			if($screen->id == 'edit-popcornlm'){
				
				?>
				<div style="margin: 0 auto; margin-top: 30px; margin-bottom: 40px; border: dotted 1px #666; width: 80%;">
			       <h1 style="padding: 5px; text-align: center; margin-bottom: 0px;">Welcome to Popcorn Analysis.</h1> <p style="padding: 10px; text-align: center;">This system allows you to hook into online video and critique, fact-check, or otherwise evaluate the content. Visitors will see your analysis show up at the point in the video where it is relevant. Developed by <a href="http://nickjamesray.com/" target="_blank">Nick Ray</a>. Popcorn.js developed by Mozilla. </p><h3 style="text-align: center;"><a href="#" class="popcornLMInstructionLink">Show Instructions</a> | <a href="post-new.php?post_type=popcornlm">Add Video</a> | <a href="edit.php?post_type=popcornlm_subjects">Manage People/Topics</a> | <a href="edit.php?post_type=popcornlm_labels">Manage Label Templates</a></h3>
			<p id="popcornLMInstructionBlock">there.</p>
			
		
			<script type="text/javascript">
			
			jQuery(".popcornLMInstructionLink").live('click',function(e){
				jQuery("#popcornLMInstructionBlock").show();
				jQuery(this).addClass("popcornLMInstructionHide").removeClass("popcornLMInstructionLink").html('Hide Instructions');
				
				e.preventDefault();
			});
			jQuery(".popcornLMInstructionHide").live("click",function(e){
				jQuery("#popcornLMInstructionBlock").hide();
				jQuery(this).addClass("popcornLMInstructionLink").removeClass("popcornLMInstructionHide").html('Show Instructions');
			});
			jQuery("#popcornLMInstructionBlock").hide();
			
			</script>
			    </div>
			
			<?php
			}elseif($screen->id == 'edit-popcornlm_labels'){
				?>
					<div style="margin: 0 auto; margin-top: 30px; margin-bottom: 40px; border: dotted 1px #666; width: 80%; text-align: center;">
					<h2>About Label Templates</h2>
					<p>Label Templates allow you to create options for analyzing your video. For example, is a statement true or false? Funny or not? <br />There is one default that is always available, which is the example below.</p>
					<?php
					$default = $this->class['labels']->getDefaultLabel();
					
					
					echo '<strong>'.$default['name'].':</strong><br /> <div style="margin: 0 auto; width: 300px; margin-bottom: 10px; margin-top: 10px;">';
					foreach($default['colors'] as $name=>$color){
						echo '<div style="float: left; margin-right: 20px; height: 20px;">'.esc_attr($name).': </div><div style="width: 20px; height: 20px; margin-right: 20px; background-color: #'.$color.'; float: left;"></div>';
					}
					?>
					
					<div style="clear: both;"></div>
					</div>
					</div>
				
				<?php
			}elseif($screen->id == 'edit-popcornlm_subjects'){
				?>
				<div style="margin: 0 auto; margin-top: 30px; margin-bottom: 40px; border: dotted 1px #666; width: 80%; text-align: center;">
				<h2>About People/Topics</h2>
				<p style=" padding: 10px 80px;">These are the people or topics covered in your video. Since they may end up in multiple videos, this is a good way to link them together and provide background info if needed.</p>
				
				</div>
				<?php
			}

		    
		}
	
	
	
	
	
	public function init(){
		$this->registerPostType();
	
	}
	
	//settings page
	public function admin(){
		require_once('admin.php');	
	}
	
	//sets up settings page
	public function adminActions(){
	//	add_options_page("Popcorn List Maker", "Popcorn List Maker",1,"Popcorn List Maker", "admin");
	add_submenu_page('edit.php?post_type=popcornlm','Popcorn List Maker Instructions','Instructions','manage_options','popcornLM_instructions',array($this,'instructionsPage'));
	}
	
	
	public function instructionsPage(){
		echo '<div style="margin-top: 50px;"><center>Coming soon!</center></div>';
	}
	
	
	
	public function moveSubmit($post){
		global $wp_meta_boxes;
		
		remove_meta_box( 'submitdiv', 'popcornLM', 'side' );
		  //  add_meta_box( 'submitdiv', __( 'Publish' ), 'post_submit_meta_box', 'popcornLM', 'normal', 'low' );
	}
	
	
	public function so_screen_layout_columns($columns ) {
	    $columns['post'] = 1;
	    return $columns;
	}
	

	public function so_screen_layout_post() {
	    return 1;
	}
	
	
	
	
	
	//registers the post type
	public function registerPostType(){
	
		  $labels = array(
		    'name' => _x('Popcorn Analysis - Videos', 'post type general name'),
		    'singular_name' => _x('Video', 'post type singular name'),
		    'add_new' => _x('Add New Video', 'book'),
		    'add_new_item' => __('Add New Video'),
		    'edit_item' => __('Edit Video'),
		    'new_item' => __('New Video'),
		    'all_items' => __('All Videos'),
		    'view_item' => __('View Video'),
		    'search_items' => __('Search Videos'),
		    'not_found' =>  __('No videos found'),
		    'not_found_in_trash' => __('No videos found in Trash'), 
		    'parent_item_colon' => '',
		    'menu_name' => __('Popcorn Analysis')
		  );
		
		  $args = array(
		    'labels' => $labels,
		    'public' => false,
		    'publicly_queryable' => false,
		    'show_ui' => true, 
		    'show_in_menu' => true, 
		    'query_var' => true,
		    'rewrite' => true,
		    'capability_type' => 'post',
		    'has_archive' => true, 
		    'hierarchical' => false,
		    'menu_position' => null,
		    'supports' => array( 'title')
		  ); 
		  register_post_type('popcornLM',$args);
	}
	
	//sets up the fields needed
	public function registerMetaBoxes(){
		add_meta_box(
				'popcornLM_info', 
				'Video-Linked Info', 
				array($this,'showPopcornBox'), 
				'popcornLM', 
				'normal', 
				'high'); 
				
		
	}
	
	public function jqueryUIMenus(){

		global $post;
		wp_enqueue_script('jquery');
		wp_enqueue_script('suggest');
		wp_enqueue_script('jquery-ui-slider');
		wp_enqueue_style('jquery-ui-custom', plugin_dir_url(__FILE__).'css/jquery-ui-1.8.23.custom.css'); 
?>
<link rel="stylesheet" href="<?php echo plugin_dir_url(__FILE__).'css/style.css';?>" />
<script type="text/javascript" src="<?php echo plugin_dir_url(__FILE__).'js/popcorn-ie8.js';?>"></script>

<script type="text/javascript">

jQuery(document).ready(function(){
	
	if(jQuery('#<?php echo $this->prefix.'youtube'?>').length!==0){
	var video = jQuery('#<?php echo $this->prefix.'youtube'?>').val();
	if((video!=='')&&(ytVidId(video)!=0)){
		jQuery('#adminPopcorn').height(276);
		var pop = Popcorn.youtube('#adminPopcorn',video);
		
		pop.on("timeupdate",function(){
			var time = Math.floor(this.currentTime());
			this.emit("timeChange",{
				time : time
				});
			});

			pop.on("timeChange",function(data){
				jQuery('#videoTime').html(secondsToMinutesHours(data.time));
				jQuery('#videoTimeHidden').val(data.time);
			});
		
		
		
		jQuery('#youtubeLinkField').hide();
		jQuery('#youtubeActiveLink').html('<p>Active Link: '+video+'</p><a class="button-secondary" href="#" id="changeVideo" title="Change Video">Change Video</a>');
		
	
		
	}else if(video!==''){
		alert('YouTube link is not valid. Please double check.');
	}else{
		jQuery('#initOptions').html('').hide();
	}
	
	
	}

	function changeVideo(){
		alert('WARNING: You should delete all existing resources before linking to new video.');
		jQuery('#youtubeActiveLink').hide();
		jQuery('#youtubeLinkField').show();
		jQuery('#adminPopcorn').height(0);
		pop.pause();
		jQuery('#videoCancel').html('<a href="#" id="cancelChangeVideo">Keep current video</a>');
		jQuery('#videoSubmit').val('Link to New Video');
	}

	jQuery('#changeVideo').live('click',function(e){
		e.preventDefault();
		changeVideo(pop);
	});

	jQuery('#cancelChangeVideo').live('click',function(e){
		e.preventDefault();
		jQuery('#adminPopcorn').height(276);
		jQuery('#youtubeLinkField').hide();
		jQuery('#youtubeActiveLink').show();
	});

	function ytVidId(url) {
		var p = /^(?:https?:\/\/)?(?:www\.)?youtube\.com\/watch\?(?=.*v=((\w|-){11}))(?:\S+)?$/;
		if(url.match(p)){
			return RegExp.$1;
		}else{
			return 0;
		}
	}
		
	function addZero(num){
			(String(num).length < 2) ? num = String("0" + num) :  num = String(num);
			return num;		
	}



	

		function secondsToMinutesHours(s){
			var hours = Math.floor(s/(60*60));

			var divisor_for_minutes = s % (60*60);
			var minutes = Math.floor(divisor_for_minutes/60);

			var divisor_for_seconds = divisor_for_minutes % 60;
			var seconds = Math.ceil(divisor_for_seconds);

			var time = addZero(hours)+":"+addZero(minutes)+":"+addZero(seconds);
			return time;
		}

		
		//repeatable script
		jQuery('.repeatable-add').click(function() {
			field = jQuery(this).closest('td').find('.custom_repeatable li:last').clone(true);
			fieldLocation = jQuery(this).closest('td').find('.custom_repeatable li:last');
			jQuery('input', field).val('').attr('name', function(index, name) {
				return name.replace(/(\d+)/, function(fullMatch, n) {
					return Number(n) + 1;
				});
			})
			field.insertAfter(fieldLocation, jQuery(this).closest('td'))
			return false;
		});

		jQuery('.repeatable-remove').click(function(){
			jQuery(this).parent().remove();
			return false;
		});

	
		//this needs changed so there is ONE button that adds, and each one has a delete next to it.
		jQuery('.subjectAdd').live('click',function(e){
			
		var parent = jQuery(this).parent();	jQuery(this).hide().parent().find('.singleSubjectInput').show().find('.singleSubjectField').suggest(ajaxurl + '?action=popcornlm-subject-list', {
				delay: 500,
				minchars: 2,
				onSelect: function(){
					var data = {
						action: 'popcornlm-single-subject',
						subject : this.value
						};

					jQuery.getJSON(ajaxurl, data, function(response){
						
						var output = '';
						if(response.data.image!==undefined){
							output += '<img class="singleSubjectPreviewImg" src="'+response.data.image+'" />';
							output += '<p style="text-align: center; font-weight: bold;">';
							}else{
								output += '<p style="text-align: center; font-weight: bold; margin-top: 50px;">';
							}
							output += response.data.title+'<br /><a href="#" class="subjectRemove">(remove)</a></p>';
						now = Math.round((new Date()).getTime() / 1);
						parent.find('.singleSubjectPreview').html(output).show();
						parent.find('.singleSubjectInput').hide();
						parent.find('.singleSubjectField').val(response.data.id);
						
						parent.parent().append('<div class="singleSubject"><a class="subjectAdd button" href="#">Add <br />Speaker/Topic</a><div class="singleSubjectInput" style="display: none;"><small>Type and choose from the list that appears.</small><input type="text" name="singleSubject['+now+']" id="" class="singleSubjectField"  onkeypress="if(event.keyCode==13) return false;" value="" size="10" />	<br /><a class="subjectCancelAdd" href="#">Cancel</a></div><div class="singleSubjectPreview" style="display: none;"></div></div>');
						//alert(response.data);
					});
				}
				
			});
			e.preventDefault();
			//addSuggest();
		});
		
		jQuery('.subjectRemove').live('click',function(e){
			if (confirm("Are you sure you want to remove this speaker/topic? Doing so will unlink any comment blocks you have added. In other words, a pain to fix.")) {
			      jQuery(this).parent().parent().parent().find('.subjectAdd').show();
				  jQuery(this).parent().parent().parent().find('.singleSubjectField').val('');
				  jQuery(this).parent().parent().html('');
			
			
			   }
			e.preventDefault();
		});
		
		jQuery('.subjectCancelAdd').live('click',function(e){
			jQuery(this).parent().hide().parent().find('.subjectAdd').show();
			e.preventDefault();
		});
	
		
		function addSuggest(){
			jQuery(".singleSubjectField").suggest(ajaxurl+ "?action=popcornlm-subject-list", { 
				delay: 500, 
				minchars: 2,
				onSelect: function(){
					var data = {
						action: 'popcornlm-single-subject',
						subject : this.value
						};

					jQuery.getJSON(ajaxurl, data, function(response){
						
						
						
						console.log(response);
						//alert(response.data);
					});

				}
				});
		}
		
		
		jQuery('#vidOutcomeTemplate').live('change',function(){
			
			
			var id = jQuery(this).find('option:selected').attr('value');
			
			var data = {
				action: 'popcornlm-template-meta',
				id: id
			};
			
		    jQuery.getJSON(ajaxurl, data, function(response){
			
				//alert(response.data.labelVals[0]);
				jQuery('#templatePreviewName').html(response.data.name);
				var output = '';
				jQuery.each(response.data.labelVals,function(key,val){
					output += '<div class="templatePreviewColInstance"><div class="templatePreviewColName">'+val.label+'</div><div class="templatePreviewColSwatch" style="background-color: #'+val.col+'; "></div></div>';
				});
				output += '<div style="clear: both"></div>';
				jQuery('#templatePreviewColContainer').html(output);
				
			});
		
		
		});

		

		
		
	});



</script>


<?php

	}
	
	
	public function showPopcornBox($post) {
		
		
		$custom_meta_fields = $this->custom_meta_fields;
	
	// Use nonce for verification
	echo '<input type="hidden" name="custom_meta_box_nonce" value="'.wp_create_nonce(basename(__FILE__)).'" />';
		?>
		<input type="hidden" id="popcornStep" name="popcornStep" value="1" />
		<div id="adminPopcornWrapper">
		<div id="adminPopcorn" ></div>
		<?php
		
		
		
		//video is unique
		$videoMeta = get_post_meta($post->ID, $this->prefix.'youtube', true);
		
		
		
		
			echo '<div id="youtubeLinkField" ><label for="'.$this->prefix.'youtube'.'">'.'YouTube Link: '.'</label>
				<input type="text" name="'.$this->prefix.'youtube'.'" id="'.$this->prefix.'youtube'.'" value="'.$videoMeta.'" size="30" />
				<br /><span class="description">Paste in the URL for the YouTube video. Example: http://youtube.com/watch?v=SOMEID</span><br /><br /><input class="button-primary" type="submit" name="save" value="Link to Video" id="videoSubmit"><span id="videoCancel"></span></div><div id="youtubeActiveLink"></div>';
		
		?>
		</div>
		
		<div id="initOptions" >
		<?php $optionsMeta = get_post_meta($post->ID,'popcornLMOptions',true);
			if($optionsMeta){
				$optionsObject = json_decode($optionsMeta);
				print_r($optionsObject);
				$subjects = get_object_vars($optionsObject->subjects);
				$vidOutcomeTemplate = $optionsObject->vidOutcomeTemplate;
				if($subjects!=''&&$vidOutcomeTemplate!=''){
					//everything is set up, proceed as planned.
				
				}else{
					?>
					<script type="text/javascript">
					jQuery(document).ready(function(){
						jQuery('#addVideoLinkBox').html('').hide();
						jQuery('#initOptions').show();
					});
					</script>
					<?php
				}
			}

				?>
				
				
				
				
				<h2>Step 2: Configure</h2>
				<p>Choose what labels you will be applying to your analysis, example True/False. These can be created and customized by going to "Label Templates" under "Popcorn Analysis" in the left menu. <strong>Changing this in the future is not recommended, as it will require relabeling your content.</strong></p>
				
			<label id="vidOutcomeLabel" style="margin-top: 5px; margin-right: 10px;" for="vidOutcomeTemplate">Outcome Template: </label>
				
				<select name="vidOutcomeTemplate" id="vidOutcomeTemplate">
				
					<?php
				 	$default = $this->class['labels']->getDefaultLabel();
					
					
					
					echo '<option value="default" ';
					if($vidOutcomeTemplate=='default'){
						echo 'selected="selected"';
					}
					
					echo '>'.$default['name'].'</option>';
					
					$outcomeTemplates = new WP_Query(array('post_type'=>'popcornlm_labels','posts_per_page'=>-1));
			while ( $outcomeTemplates->have_posts() ) : $outcomeTemplates->the_post();
				
					

					echo '<option value="'.get_the_ID().'" ';
					if($vidOutcomeTemplate==get_the_ID()){
						echo 'selected="selected"';
					}
					echo '>'.get_the_title().'</option>';
					endwhile;
					
					?>
		
				</select>
				<div id="templatePreview" style="margin: 0 auto; width: 450px; padding: 5px; border-bottom: dotted 1px #666; margin-bottom: 15px;">
				<?php
				$labelMeta = $this->class['labels']->getLabelMeta($vidOutcomeTemplate);
				if($labelMeta){
					echo '<p style="text-align: center;padding-top: 5px; margin-top: 5px;">Template Preview: <span id="templatePreviewName" style="font-weight: bold;">'.$labelMeta['name'].'</span></p><div id="templatePreviewColContainer" style="margin: 0 auto;width: 300px;">';
					foreach($labelMeta['colors'] as $name=>$color){
						echo '<div class="templatePreviewColInstance"><div class="templatePreviewColName">'.$name.'</div><div class="templatePreviewColSwatch" style="background-color: #'.$color.'; "></div></div>';
					}
					echo '<div style="clear: both"></div></div>';
				}
				?>
				</div>
				
				<?php
			
				
				?>
				<div id="subjectList" style="margin-top: 10px; padding: 5px;">
				<p>Add people/topics here, e.g. John Smith, Jane Doe. Fewer the better, as each gets a column. Generally this will be a speaker. <em>"Spare the brevity, spoil the layout."</em><br />You can add people/topics by going to "People/Topics" under "Popcorn Analysis" in the left menu.</p>
				<?php
				
				if($subjects!=''&&is_array($subjects)){
					foreach($subjects as $id=>$val){
						
					}
				}
				
				
				?>
	
				<div class="singleSubject">
				<a class="subjectAdd button" href="#">Add <br />Speaker/Topic</a>
				<div class="singleSubjectInput" style="display: none;">
				<small>Type and choose from the list that appears.</small>
				<input type="text" name="singleSubject[<?php echo time(); ?>]" class="singleSubjectField"  onkeypress="if(event.keyCode==13) return false;" value="" size="10" />	<br /><a class="subjectCancelAdd" href="#">Cancel</a></div>
				<div class="singleSubjectPreview" style="display: none;"></div>
				</div>
					
				</div>
				<div style="clear: both;"></div>	
				
				
				<br /><br /><input name="original_publish" type="hidden" id="original_publish" value="Publish" />
						<input type="submit" name="publish" id="publish" class="button-primary" value="Configure" tabindex="5" accesskey="p"  />
	
		
		</div>
		
		<div id="addVideoLinkBox">
		<input type="hidden" name="videoTime" id="videoTimeHidden" value="0" />
		
		<input type="hidden" name="timeOfPost" id="timeOfPost" value="<?php echo time();?> "/>
		
		<h2>Step 3,4,5...: Add a Video Link</h2>
		<p>Scrub the player until the time you want shows up below, then fill out the fields and click "Add Video Link."</p>
		
		<p>Time: <span id="videoTime"></span></p>
		
		
		<?php
		$args = array(
			'textarea_rows'=>4
			
		);
		
		
		wp_editor('','popcorninfobody',$args);
		
	
		 ?>
		
		</div>
		
		<?php
		//this will pull all of the resources for this video in JSON format.
		$linkBlock = get_post_meta($post->ID);
		
		
		
		if($linkBlock){
			foreach($linkBlock as $k=>$block){
				if($k!='_edit_last'&&$k!='_edit_lock'&&$k!='popcornLM_youtube'){
					$linkBlockTest = explode('__',$k);
					if($linkBlockTest[0]=='popcornLM_linkBlock'){
						if(isset($linkBlockTest[1])){
							
							echo $linkBlockTest[1];
							
							
						}
					}
					
					
				}
				
			}
			
		}
		
		?>
		
		
		<?php
	}

	

	// Save the Data
	public function savePopcornMeta($post_id) {




		// verify nonce
		if (!wp_verify_nonce($_POST['custom_meta_box_nonce'], basename(__FILE__))) 
			return $post_id;
		// check autosave


		if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE)
			return $post_id;
		// check permissions
		if ('page' == $_POST['post_type']) {
			if (!current_user_can('edit_page', $post_id))
				return $post_id;
		} elseif (!current_user_can('edit_post', $post_id)) {
			return $post_id;
		}


		if("popcornlm"==$_POST['post_type']){
			
			
			
			$popcornArray = array();
			if(isset($_POST[$this->prefix.'youtube'])){
				$popcornArray[$this->prefix.'youtube'] = $_POST[$this->prefix.'youtube'];
			}
			
			
			$optionsArray = array();
			if(isset($_POST['vidOutcomeTemplate'])){
				$optionsArray['vidOutcomeTemplate'] = $_POST['vidOutcomeTemplate'];
			}
			
			if(isset($_POST['singleSubject'])&&is_array($_POST['singleSubject'])){
				
				foreach($_POST['singleSubject'] as $k=>$v){
					if($v!=''){
						//v is the ID
						$optionsArray['subjects'][$k] = $v;
					}
				}
				$popcornArray['popcornLMOptions'] = json_encode($optionsArray);
			}
			
			
			
			//more to add, but for now...
			foreach($popcornArray as $field=>$value){
				$old = get_post_meta($post_id, $field, true);
				$new = $value;
				if($new && $new != $old){
					update_post_meta($post_id,$field,$new);
				}elseif(''==$new && $old){
					delete_post_meta($post_id,$field,$old);
				}
	
			}
			
			
			
			
			
			// $custom_meta_fields = $this->custom_meta_fields;
			// 		$subFields = $this->subFields;
			// 		$resource = array();
			// 		foreach($subFields as $subField){
			// 			$resource[$subField['id']] = $_POST[$subField['id']];
			// 		}
			// 
			// 		$encode = json_encode($resource);
			// 
			// 		// loop through fields and save the data
			// 		foreach ($custom_meta_fields as $field) {
			// 			//youtube and content blocks are distinct
			// 
			// 			if($field['id']==$this->prefix.'linkBlock'){
			// 
			// 				$time = $_POST['timeOfPost'];
			// 
			// 				$new = $encode;
			// 
			// 				$key = $field['id'].'__'.$time;
			// 
			// 				$old = get_post_meta($post_id, $key, true);
			// 
			// 				if ($new && $new != $old && $time) {
			// 					update_post_meta($post_id, $key, $new);
			// 				} elseif ('' == $new && $old) {
			// 					delete_post_meta($post_id, $key, $old);
			// 				}
			// 			}elseif($field['id']==$this->prefix.'optionsBlock'){
			// 
			// 			}else{
			// 				$old = get_post_meta($post_id, $field['id'], true);
			// 
			// 				$new = $_POST[$field['id']];
			// 				if ($new && $new != $old) {
			// 					update_post_meta($post_id, $field['id'], $new);
			// 				} elseif ('' == $new && $old) {
			// 					delete_post_meta($post_id, $field['id'], $old);
			// 				}
			// 			}
			// 
			// 		} // end foreach



		}elseif("popcornlm_labels"==$_POST['post_type']){

			if(!empty($_POST['col'])&&is_array($_POST['col'])){

				$labelArray = array();
				foreach($_POST['col'] as $id=>$col){
					//id is the time, should match with the id from $_POST['label']
					if(!empty($_POST['label'][$id])&&$_POST['label'][$id]!==''){
						//both color and name were set

						//needs sanitized
						$labelArray[$id]['label'] = esc_attr($_POST['label'][$id]);

						$labelArray[$id]['col'] = $col;
					}


				}
				//ready to json encode the array. meta key is 'labelVals'
				$new = json_encode($labelArray);
				$old = get_post_meta($post_id,'labelVals',true);
				if($new && $new != $old){
					update_post_meta($post_id,'labelVals',$new);
				}elseif(''==$new && $old){
					delete_post_meta($post_id,'labelVals',$old);
				}


			}
		}elseif("popcornlm_subjects"==$_POST['post_type']){
			
			$subjectArray = array();
			$subjectArray['popcornsubjectinfo'] = $_POST['popcornsubjectinfo'];
			$subjectArray['subjectThumb'] = $_POST['subjectThumb'];
			$subjectArray['subjectSubhead'] = esc_attr($_POST['subjectSubhead']);

			foreach($subjectArray as $k=>$v){
				$new = $v;
				$old = get_post_meta($post_id,$k,true);
				if($new && $new != $old){
					update_post_meta($post_id,$k,$new);
				}elseif(''==$new && $old){
					delete_post_meta($post_id,$k,$old);
				}

			}

		}

	}






}


$popcornLM = new PopcornLM();











//initialize action hooks



?>