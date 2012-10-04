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
require_once(dirname(__FILE__).'/lib/source.php');

require_once(dirname(__FILE__).'/lib/display.php');
require_once(dirname(__FILE__).'/lib/ajax.php');



//Originally called Popcorn List Maker. PopcornLM is preserved to avoid plugin conflicts.
class PopcornLM {
	
	public $class;
	
	
	static $add_popcorn;
	static $popcorn_script;
	
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
		$popcornLM_Source = new PopcornLM_Source();
		$popcornLM_Label = new PopcornLM_Label();
		$popcornLM_Ajax = new PopcornLM_Ajax();
		
		
		$this->class = array(
			'subjects'=>$popcornLM_Subject,
			'labels'=>$popcornLM_Label,
			'sources'=>$popcornLM_Source
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
		
		if(is_admin()){
		//	add_action('init','addPopcornLMThickbox');
		}
		
		
		
		add_shortcode('popcornLM', array($this,'shortcode'));
	
		$this->setClasses();
	
		
	}
	
	
	public function addPopcornLMThickbox(){
		//Enqueue thickbox - for plugin file
	//	wp_enqueue_style('thickbox');
		wp_enqueue_script('thickbox');
	}
	
	//public fu
	
	public function shortcode($atts){
		//first we need to get that title and meta
		if(isset($atts['id'])){
			echo '<link rel="stylesheet" href="'.POPCORNLM_PATH.'css/display.css" />';
			$id = $atts['id'];
			echo '<div id="popcornLMDisplayContainer">';
			echo '<h3>'.get_the_title($id).'</h3>';
			
			//now check for a video. if there isn't one, say the video is unavailable and return.
			$custom = get_post_custom($id);
			
			if(isset($custom['popcornLM_youtube'][0])&&$custom['popcornLM_youtube'][0]!=''){
				?>
				
	
				<div id="popcornLMResourceColumns">
				<?php
				if(isset($custom['resourceBlock'])&&is_array($custom['resourceBlock'])&&isset($custom['popcornLMOptions'])&&$custom['popcornLMOptions'][0]!=''){
					//all configured properly. let's do this thing!
					echo '<div id="popcornLMVideo"></div>';
					echo '<div id="popcornLMAlert"></div>';
					$sortedTimes = array();
					$options = json_decode($custom['popcornLMOptions'][0]);
					$options = get_object_vars($options);
					
					
					$labelMeta = $this->class['labels']->getLabelMeta($options['vidOutcomeTemplate']);
					$subjects = get_object_vars($options['subjects']);
					if($labelMeta&&$subjects){
						$columns = count($subjects);
						echo '<style>.resourceListDisplayColumn{width: '.(92/$columns).'%; margin-left: '.(1/$columns).'%; margin-right: '.(1/$columns).'%; float: left; padding: '.(2/$columns).'%;  }';
						foreach($labelMeta['colors'] as $id=>$label){
							echo '.popcornLMBlockShell-'.$id.'{background-color:#'.$label['col'].';}';
						}
						
						echo '</style>';

						//we have the meta! Let's sort our resources so they code with popcorn properly (e.g. chronological)
						$columnSort = array();
						foreach($custom['resourceBlock'] as $resourceBlock){
							$block = maybe_unserialize($resourceBlock);
							//we include the id here in case there are duplicate times anywhere.
							$sortedTimes[$block['time']][$block['id']] = $block['time'];
							
							if($block['label']!='none'){
								$block['labelName'] = $labelMeta['colors'][$block['label']]['label'];
							}
							foreach($subjects as $id=>$postId){
								
								if($block['subject']==$postId){

									//so we can sort this by time too
									$columnSort[$postId][$block['time']][$block['id']] = $block;
									
						
								}
						
							}
							
						}
							//one big array. now we need to structure our columns.
						
							
							foreach($subjects as $id=>$val){
								
								krsort($columnSort[$val]);
								echo '<div class="resourceListDisplayColumn" id="subject_'.$val.'">';
								$subjectMeta = get_post_custom($val);
								$subjectTitle = get_the_title($val);
								//print_r($subjectMeta);
							//	echo '<div class="popcornLMAlert"></div>';
									//we want to display photo on the left with name on the right
									echo '<div class="resourceListDisplayHead">';
									echo '<div class="resourceListDisplayHeadImage">';
									if(isset($subjectMeta['subjectThumb'][0])&&$subjectMeta['subjectThumb'][0]!=''){
										$imageShow = true;
										$subjectImage = wp_get_attachment_image_src($subjectMeta['subjectThumb'][0], 'popcornlm-subject-thumb');
										$subjectImage = $subjectImage[0];
										echo '<img src="'.$subjectImage.'" />';
									}else{
										$imageShow = false;
									}
									echo '</div>';
									echo '<div class="popcornLMDisplayHeadText">';
									echo '<h4 class="popcornLMDisplayHeadTitle"';
									if($imageShow!=true){
									//	echo 'style="margin-left: 30%;"';
									}
									echo '>'.$subjectTitle.'</h4>';
									if(isset($subjectMeta['subjectSubhead'][0])&&$subjectMeta['subjectSubhead'][0]!=''){
										echo '<p class="popcornLMDisplayHeadByline">'.$subjectMeta['subjectSubhead'][0].'</p>';
										//there will be a popcornsubjectinfo call here with an "info link" to bring a pop up. not yet.
									}else{
										//nothing should happen, this just helps us with design.
									
									}
									echo '</div>';
									echo '</div><div style="clear: both;"></div>';
								
								
								foreach($columnSort[$val] as $time=>$blockArray){
									$zIndex = 1;
									
								
									foreach($blockArray as $id=>$blockInfo){
											
									if($blockInfo['title']!=''&&$blockInfo['text']!=''){
										
										echo '<div class="popcornLMBlockShell popcornLMBlockShell-'.$blockInfo['label'].'" rel="'.$blockInfo['time'].'_'.trim($blockInfo['id']).'" style="z-index:'.$zIndex.';">';
										echo '<div class="popcornLMBlockInner">';
										echo '<div class="popcornLMBlockTitle">';
									echo '<h4>'.$blockInfo['title'].'<br />';
									echo '<span class="popcornLMBlockTime">'.gmdate('H:i:s',$blockInfo['time']).'</span>';
									echo'</h4>';
								
										echo '<div class="popcornLMBlockLabelLink"><a class="popcornLMBlockExpand" >Expand</a>';
									
								
											if(isset($labelMeta['colors'][$blockInfo['label']]['label'])){
											echo '<p class="popcornLMHiddenLabel">';
											
											echo $labelMeta['colors'][$blockInfo['label']]['label'].'</p>';
											echo '<div style="clear: both;"></div>';
										}else{
											
											echo '<div style="clear: both;"></div>';
										}
									
										echo '</div></div>';
										echo '<div class="popcornLMBlockBody">';
										echo html_entity_decode($blockInfo['text']);
										echo '</div><div style="clear: both;"></div>';
										if(isset($blockInfo['sources'])&&is_array($blockInfo['sources'])){
											$sources = count($blockInfo['sources']);
											if($sources>1){
												$sourceHead = 'Sources';
											}else{
												$sourceHead = 'Source';
											}
											
											
											echo '<h6 class="popcornLMSourceListTitle">'.$sourceHead.':</h6>';
											echo '<ul>';
											foreach($blockInfo['sources'] as $id=>$sourceId){
												if(is_numeric($sourceId)){
													$name = get_the_title($sourceId);
													$url = get_post_meta($sourceId,'sourceUrl',true);

													echo '<li>';
													if($url!=''){
														echo '<a href="'.$url.'" class="popcornLMSourceLink" target="_blank">'.$name.'</a>';
													}else{
														echo $name;
													}
													$types = wp_get_object_terms($sourceId,'source_types');
													if(!empty($types)){
														$counter = 0;
														//counter so we only put comma after first one, not before
														echo '<span class="popcornLMSourceType">';
														foreach($types as $type){
															if($counter>0){
																echo ', ';
															}
															echo $type->name;
															$counter++;
														}
														echo '</span>';
													}


													echo '</li>';
												}

											}
											echo '</ul>';
											
										}
										
										
										echo '</div></div>';
									}
									}
								}
								echo '</div>';
							}
							ksort($sortedTimes);

								
					}
				}//ends the top if
				
			//	print_r($sortedBlocks);
			//now we need to write the javascript that makes this run proper.
			//not optimized!!
			 $script = '<script type="text/javascript" src="'.POPCORNLM_PATH.'js/popcorn-complete.min.js"></script>';
			
			 		$script .= '<script type="text/javascript">';
				
			 		$script .= 'jQuery(window).load(function(){';
		 			$script .= "
		
					
		
					//in here we add the data attr for height to each panel
					var columnWidth = jQuery('.resourceListDisplayColumn:first').width();

					
					jQuery('.popcornLMBlockShell').each(function(index){
					//minus 12 to account for padding.	jQuery(this).css({'position':'absolute','visibility':'hidden','display':'block','width':columnWidth-12});
						var	subHead = jQuery(this).find('.popcornLMBlockTitle');
							 title = subHead.outerHeight(true);
							//title = title+10;
							//so our images don't overflow.
							jQuery(this).find('img').css({'max-width':'97%','width':'auto','height':'auto'});
							shell = jQuery(this).outerHeight(true);
							jQuery(this).data('fullHeight',shell);
							jQuery(this).data('titleHeight',title);
							var thisRel = jQuery(this).attr('rel');
						
							var relData = thisRel.split('_');
							jQuery(this).data('blockTime',relData[0]);
							jQuery(this).data('blockId',relData[1]);
							jQuery(this).data('alerted','no');
							var color = jQuery(this).css('background-color');
							var thisColumn = jQuery(this).closest('.resourceListDisplayColumn');
							var columnName = thisColumn.find('.popcornLMDisplayHeadTitle').html();
							
							var thisLabel = jQuery(this).find('.popcornLMHiddenLabel').html();
							if(thisLabel!==null){
								jQuery('#popcornLMAlert').append('<div class=\"popcornLMAlertBox\" id=\"popcornLMAlertBox-'+relData[1]+'\" rel=\"'+relData[1]+'\" style=\"border: solid 3px '+color+'; display: none;\"><h4>'+thisLabel+'</h4><p>'+columnName+'</p></div>');
								
							}
							
							
			jQuery(this).css({'position':'static','visibility':'visible','display':'none','width':''});
					});
		
		";
			 		$script .= 'var pop = Popcorn.youtube("#popcornLMVideo","'.$custom['popcornLM_youtube'][0].'");';
				//	$script .= 'var pop = Popcorn.vimeo("#popcornLMVideo","http://player.vimeo.com/video/49646272");';	
					if(count($sortedTimes)>=1){
						
						$script .= "
						jQuery('.resourceListDisplayColumn').each(function(index,v){
							jQuery(this).find('.popcornLMBlockExpand:last').hide();
							jQuery(this).find('.popcornLMBlockShell:last').addClass('popcornLMLastBlock');
						fullHeight = jQuery(this).find('.popcornLMBlockShell:last').data('fullHeight');
					fullHeight = fullHeight-10;	
					jQuery(this).find('.popcornLMBlockShell:last').data('titleHeight',fullHeight);
						});
					
						
						jQuery('.popcornLMBlockExpand').live('click',function(e){
						var block = jQuery(this).closest('.popcornLMBlockShell');
						newHeight = block.data('fullHeight');
						newHeight = newHeight-10;
						block.animate({'height':newHeight},400);
						block.addClass('popcornLMHover');
						jQuery(this).addClass('popcornLMBlockCollapse').removeClass('popcornLMBlockExpand').html('Collapse');
						e.preventDefault();	
						});
						
						jQuery('.popcornLMBlockCollapse').live('click',function(e){
							var block = jQuery(this).closest('.popcornLMBlockShell');
							newHeight = block.data('titleHeight');
							block.animate({'height':newHeight},400);
							block.removeClass('popcornLMHover');	
						jQuery(this).addClass('popcornLMBlockExpand').removeClass('popcornLMBlockCollapse').html('Expand');					e.preventDefault();
						});
						
						
						
						function blockTiming(currentTime){
							jQuery('.popcornLMBlockShell').each(function(index){
								//data converted our number to a string. change it back so we can do comparison operators!
								var blockTime = parseInt(jQuery(this).data('blockTime'));
								
								if(blockTime>currentTime){
								
									//hide alert
									var blockId = jQuery(this).data('blockId');
							jQuery('#popcornLMAlertBox-'+blockId).hide();
								
									
									
									//hasnt happened yet
								jQuery(this).removeClass('popcornLMOn');
								jQuery(this).stop(true,true).animate({'height': 0},400,function(){
									jQuery(this).hide().height(0);
								});
									
								}else if(blockTime<currentTime){
									
									if(blockTime+3<currentTime){
										
										//hide alert
										var blockId = jQuery(this).data('blockId');
										
								jQuery('#popcornLMAlertBox-'+blockId).hide();
									}else{
											//show alert
			var blockId = jQuery(this).data('blockId');									jQuery('#popcornLMAlertBox-'+blockId).show();
									}
									
									
									if(!jQuery(this).hasClass('popcornLMOn')){
										
							newHeight = jQuery(this).data('titleHeight');
							
									jQuery(this).addClass('popcornLMOn');	jQuery(this).stop(true,true).height(0).show().animate({'height': newHeight},400);
							
									
									}
									
									
								
								}else if(blockTime==currentTime){
									
									
								}
							});

						}
						jQuery('.popcornLMBlockShell').hide();
						
						//The function below this runs several times a second, but we only want our code to run once for each second. This ensures that happens.
						var once = -1;
						pop.on('timeupdate',function(){
						
							
							//here we round the current time down (because it's given as a decimal) and set it equal to time.
							var time = Math.floor(this.currentTime());

							//this conditional checks first if time is equal to once (if this second has already run code).
							if( time!==once){

								//we change the once variable so this won't run more than once a second 
								once = time;

								// .emit is another Popcorn function, which says run this
								this.emit('blockUpdate',{

									//when this is run, data will pass to the pop.on below
									thisTime : time
									//thats all for now.

								});
							}
							//if the user pauses the video, they might scrub back, so we reset 'once' to -1.
							if(this.paused()){
								once = -1;
							}
							
						});
						
						pop.on('blockUpdate',function(data){
							blockTiming(data.thisTime);
						});
						
						";
						
						
						
						
			
						
					}
						
					$script .= '});';
					
			 		$script .= '</script>';
			 		
							echo $script;
	
				?>
				<div style="clear: both;"></div>

				</div>
				
				<?php
				
				
				
				
				
				
				
				
				
			
				
				
			}else{
				echo '<p>This video is unavailable. Please check back soon.</p>';
				return;
			}
			
			
			
			
			echo '</div>';
		}else{
			return;
		}
		
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
			       <h1 style="padding: 5px; text-align: center; margin-bottom: 0px;">Welcome to Popcorn Analysis.</h1> <p style="padding: 10px; text-align: center;">This system allows you to hook into online video and critique, fact-check, or otherwise evaluate the content. Visitors will see your analysis show up at the point in the video where it is relevant. Developed by <a href="http://nickjamesray.com/" target="_blank">Nick Ray</a>. Popcorn.js developed by Mozilla. <a href="#" id="fullCreditList">View Full Credits.</a></p><h3 style="text-align: center;"><a href="#" class="popcornLMInstructionLink">Show Instructions</a> | <a href="post-new.php?post_type=popcornlm">Add Video</a> | <a href="edit.php?post_type=popcornlm_subjects">Manage People/Topics</a> | <a href="edit.php?post_type=popcornlm_labels">Manage Label Templates</a></h3>
			<div id="popcornLMInstructionBlock"><h3>Step 1: Find a Video</h3>
			<p>Look on YouTube for a video you would like to critique (other video services available in a future release). This could be a political debate, remix video, or commentary - anything really!</p>
			
			<h3>Step 2: Decide & Create Labels</h3>
			<p>How are you going to judge the video? True or untrue? Funny or not? Decide what labels you will apply, then choose "Manage Label Templates" above to create the template/color scheme, or choose from what is already available.</p>
			
			<h3>Step 3: Choose your People/Topics</h3>
			<p>Is this a speech by Obama? A discussion of climate change? Search for your person or topic under "Manage People/Topics" above. If the record doesn't exist yet, create a new entry!</p>
			
			<h3>Step 4: Set up your Video</h3>
			<p>Now you can create a new video using the links below. Inside, you will first need to paste in your YouTube link, and then choose your label template and people/topics you want to critique. Note that changing these after the fact is not recommended, so plan ahead.</p>
			
			<h3>Step 5: Judge away!</h3>
			<p>Scrub to the time in the video you want to critique, provide a title, choose your label, person/topic, and some text. If there are sources to support your claims, add them under "Sources" in the left column, then link to those sources in the form.</p>
			
			<h3>Step 6: Publish</h3>
			<p>At the top of the video edit screen, you should see a "shortcode." Copy/paste this into an existing or new Page in Wordpress, and watch the magic happen.</p>
			
			</div>
			
		
			<script type="text/javascript">
			jQuery("#fullCreditList").live('click',function(e){
				alert('Info panel concept influenced by Land of Opportunity, Luisa Dantas.\n\nOther open-source software used includes jscolor.js.');
			e.preventDefault();	
			});
			
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
					foreach($default['colors'] as $color){
						echo '<div style="float: left; margin-right: 20px; height: 20px;">'.esc_attr($color['label']).': </div><div style="width: 20px; height: 20px; margin-right: 20px; background-color: #'.$color['col'].'; float: left;"></div>';
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
			}elseif($screen->id == 'edit-popcornlm_sources'){
					?>
					<div style="margin: 0 auto; margin-top: 30px; margin-bottom: 40px; border: dotted 1px #666; width: 80%; text-align: center;">
					<h2>About Sources</h2>
					<p style=" padding: 10px 80px;">They boost credibility to your assessments. By putting them here, we can reuse sources in multiple videos that deal with related topics.</p>

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
	
	
	jQuery('.popcornLMBlockExpand').live('click',function(e){
	var block = jQuery(this).closest('.popcornLMBlockShell');
	newHeight = block.data('fullHeight');
	newHeight = newHeight-10;
	block.animate({'height':newHeight},400);
	block.addClass('popcornLMHover');
	jQuery(this).addClass('popcornLMBlockCollapse').removeClass('popcornLMBlockExpand').html('Collapse');
	e.preventDefault();	
	});
	
	jQuery('.popcornLMBlockCollapse').live('click',function(e){
		var block = jQuery(this).closest('.popcornLMBlockShell');
		newHeight = block.data('titleHeight');
		block.animate({'height':newHeight},400);
		block.removeClass('popcornLMHover');	
	jQuery(this).addClass('popcornLMBlockExpand').removeClass('popcornLMBlockCollapse').html('Expand');					e.preventDefault();
	});
	
	jQuery(window).load(function(){
		
	
	
	//in here we add the data attr for height to each panel
	var columnWidth = jQuery('.resourceListDisplayColumn:first').width();
	
	
	jQuery('.resourceListDisplayColumn').each(function(index,v){
		jQuery(this).find('.popcornLMBlockExpandText:last').hide();
		jQuery(this).find('.popcornLMBlockShell:last').addClass('popcornLMLastBlock');
	fullHeight = jQuery(this).find('.popcornLMBlockShell:last').data('fullHeight');
fullHeight = fullHeight-10;	jQuery(this).find('.popcornLMBlockShell:last').data('titleHeight',fullHeight);
	});
	
	jQuery('.popcornLMBlockShell').each(function(index){
	//minus 12 to account for padding.	jQuery(this).css({'position':'absolute','visibility':'hidden','display':'block','width':columnWidth-12});
		var	subHead = jQuery(this).find('.popcornLMBlockTitle');
			 title = subHead.outerHeight(true);
			//title = title+10;
			//so our images don't overflow.
			jQuery(this).find('img').css({'max-width':'97%','width':'auto','height':'auto'});
			shell = jQuery(this).outerHeight(true);
			jQuery(this).data('fullHeight',shell);
			jQuery(this).data('titleHeight',title);
			var thisRel = jQuery(this).attr('rel');
		
			var relData = thisRel.split('_');
			jQuery(this).data('blockTime',relData[0]);
			jQuery(this).data('blockId',relData[1]);
			jQuery(this).data('subjectId',relData[2]);
			jQuery(this).data('labelId',relData[3]);
			if(jQuery(this).hasClass('popcornLMLastBlock')){
				newHeight = jQuery(this).data('fullHeight')-10;
			}else{
				newHeight = jQuery(this).data('titleHeight');
			}
			
				
				
			jQuery(this).addClass('popcornLMOn');	
			jQuery(this).stop(true,true).height(0).show().animate({'height': newHeight},400);
			
			
			
			
			
	});
	
	
	});
	
	
	
	
	
	
	
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
				jQuery('.videoTime').html(secondsToMinutesHours(data.time));
				jQuery('#videoTimeHidden').val(data.time);
				jQuery('#updateVideoTimeHidden').val(data.time);
			});
		
		
		
		jQuery('#youtubeLinkField').hide();
		jQuery('#youtubeActiveLink').html('<p>Active Link: '+video+'</p><span class="configLinks"><a  href="#" id="changeVideo" title="Change Video">Change Video</a></span>');
		
	
		
	}else if(video!==''){
		alert('YouTube link is not valid. Please double check.');
	}else{
		jQuery('#initOptions').html('').hide();
		jQuery('#addVideoLinkBox').html('').hide();
	}
	
	
	}

	function changeVideo(){
		if(confirm("WARNING: Changing the video source after adding resources will likely cause you a headache. The resources will still work, but the times will probably not match up with a new resource (unless you know what you're doing). Do you still wish to proceed?")){
			jQuery('#youtubeActiveLink').hide();
			jQuery('#youtubeLinkField').show();
			jQuery('#adminPopcorn').height(0);
			pop.pause();
			jQuery('.changeConfig').hide();
			jQuery('#videoCancel').html('<a href="#" id="cancelChangeVideo">Keep current video</a>');
			jQuery('#videoSubmit').val('Link to New Video');
		}
		
		
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
		jQuery('.changeConfig').show();
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

	


		jQuery('.changeConfig').live('click',function(e){
			if(confirm("Warning: Changing these options will likely require relabeling of your data. Are you sure you want to proceed?")){
				jQuery('#initOptions').show();
			}
			e.preventDefault();
		});

	

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
		
		
			jQuery('.sourceAdd').live('click',function(e){

			var parent = jQuery(this).parent();	jQuery(this).hide().parent().find('.singleSourceInput').show().find('.singleSourceField').suggest(ajaxurl + '?action=popcornlm-source-list', {
					delay: 500,
					minchars: 2,
					onSelect: function(){
						var data = {
							action: 'popcornlm-single-source',
							source : this.value
							};

						jQuery.getJSON(ajaxurl, data, function(response){

							var output = '';
						
							output += response.data.title+'<br /><a href="#" class="sourceRemove">(remove)</a></p>';
							now = Math.round((new Date()).getTime() / 1);
							parent.find('.singleSourcePreview').html(output).show();
							parent.find('.singleSourceInput').hide();
							parent.find('.singleSourceField').val(response.data.id);

							parent.parent().append('<div class="singleSource"><a class="sourceAdd button" href="#">Connect to Source</a><div class="singleSourceInput" style="display: none;"><small>Type and choose from the list that appears.</small><input type="text" name="singleSource['+now+']" id="" class="singleSourceField"  onkeypress="if(event.keyCode==13) return false;" value="" size="10" />	<br /><a class="sourceCancelAdd" href="#">Cancel</a></div><div class="singleSourcePreview" style="display: none;"></div></div>');
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
	
		jQuery('.popcornLMBlockDelete').live('click',function(e){
		//first we need to confirm block delete
			if (confirm("Are you sure you want to remove this entry? This cannot be undone.")) {
				//then we need to send the ID and entry ID off to ajax to delete that entry
				var postId = jQuery('#idOfPost').val();
				var shell = jQuery(this).closest('.popcornLMBlockShell');
				var entryId = shell.data('blockId');
				
					var data = {
						action: 'popcornlm-delete-video-record',
						postId : postId,
						entryId : entryId
						};

						jQuery.post(ajaxurl, data, function(response){
							//not sure why we need to parse here?
							var response = jQuery.parseJSON(response);

							if(response.response=="success"){
								//last we need to remove from the DOM.
								
								shell.animate({'height': 0},400,function(){
									shell.hide().detach();
								});
								}else{
									alert('There was a problem. Apologies!');
									alert(response.response);
								}

							});
				
				
				
			}
			e.preventDefault();
		});
		
		jQuery('.cancelUpdate').live('click',function(e){
			jQuery('#updateVideoLinkBox').hide();
			jQuery('#addVideoLinkBox').show();
			e.preventDefault();
		});
		
		jQuery('.popcornLMBlockEdit').live('click',function(e){
			jQuery('#updateVideoLinkBox').show();
			jQuery('#addVideoLinkBox').hide();
			
			//e.preventDefault();
			var content = jQuery(this).closest('.popcornLMBlockShell').find('.popcornLMContentArea').html();
			if (jQuery("#wp-popcorninfobody-wrap").hasClass("tmce-active")){
				//popcornupdateinfobody is the ID
				tinyMCE.editors.popcornupdateinfobody.setContent(content);
		       
		    }else{
		        jQuery('#popcornupdateinfobody').val(content);
		    }
			var sources = jQuery(this).closest('.popcornLMBlockInner').find('.popcornLMSourceList li');
			sourceOutput = '';
			sources.each(function(index){
			//	var sourceType = jQuery(this).find('.popcornLMSourceType').html();
			
				var sourceId = jQuery(this).attr('rel');
				var sourceUrl = jQuery(this).find('.popcornLMSourceLink').attr('href');
				var sourceName = jQuery(this).find('.popcornLMSourceLink').html();
				
				
				var now = Math.round((new Date()).getTime() / 1);
				sourceOutput += '<div class="singleSource"><a class="sourceAdd button" href="#" style="display: none;">Connect to Source</a><div class="singleSourceInput" style="display: none;"><small>Type and choose from the list that appears.</small><input type="text" name="singleSource['+now+']" class="singleSourceField"  onkeypress="if(event.keyCode==13) return false;" value="'+sourceId+'" size="10" /><br /><a class="sourceCancelAdd" href="#">Cancel</a></div><div class="singleSourcePreview">'+sourceName+' <a href="#" class="sourceRemove">(remove)</a></div></div>';
				
				
				
				
				
				
			});
			//will change to updateSourceTable
			jQuery('#updateSourceTable').html(sourceOutput);
			var title = jQuery(this).closest('.popcornLMBlockInner').find('.popcornLMBlockTitle h4').html();
			jQuery('#updateResourceTitle').val(title);
			var subject = jQuery(this).closest('.popcornLMBlockShell').data('subjectId');
			jQuery('#updateResourceSubject').val(subject);
			var label = jQuery(this).closest('.popcornLMBlockShell').data('labelId');
			jQuery('#updateResourceLabel').val(label);
			var time = jQuery(this).closest('.popcornLMBlockShell').data('blockTime');
			//we need to update value, the text, and move the playhead to that time. we will also need to save the old value for returning.
			//#videoTimeHidden is normal, we will need one for update
			var readTime = secondsToMinutesHours(time);
			
			var id = jQuery(this).closest('.popcornLMBlockShell').data('blockId')
			jQuery('#updateVideoId').val(id);
			//now we update popcorn, which will filter the other events down.
			if(pop){
				jQuery('#videoTimeHidden').data('oldTime',pop.currentTime());
				pop.currentTime(time);
			}
		
		});
		
		
		
		
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
		
		
		

		
		
		jQuery('.removeSource').live('click',function(e){
			jQuery(this).parent().parent().detach();
			e.preventDefault();
		});
		
		
		jQuery('.sourceRemove').live('click',function(e){
			
			      jQuery(this).parent().parent().parent().find('.sourceAdd').show();
				  jQuery(this).parent().parent().parent().find('.singleSourceField').val('');
				  jQuery(this).parent().html('');
			
			e.preventDefault();
		});
		
		

		jQuery('.submitResource').live('click',function(e){
			var time = jQuery('#videoTimeHidden').val();
			var title = jQuery('#resourceTitle').val();
			var subject = jQuery('#resourceSubject').val();
			var label = jQuery('#resourceLabel').val();
			var text = get_tinymce_content();
			var id = jQuery('#timeOfPost').val();
			var postId = jQuery('#idOfPost').val();
			
			var sources = {};
			jQuery('.singleSource').each(function(index,thisElem){
			
				var source = jQuery(this).find('.singleSourceField').val();
				
				if(source!=''){
					var temp = jQuery(this).find('.singleSourceField').attr('name')
					var tempID = temp.split("[")[1].split("]")[0];
					sources[tempID] = source;
				}
	
			});

			var data = {
				action : 'popcornlm-create-video-record',
				time : time,
				title : title,
				subject : subject,
				label : label,
				text : text,
				sources : sources,
				id : id,
				postId : postId
			};
			
			
			//okay we are ready to make our ajax call!
			
			jQuery.post(ajaxurl, data, function(response){
				if(response.response=='success'){
					//we need to update the time in the box for the next entry.
					var now = Math.round((new Date()).getTime() / 10);
					jQuery('#timeOfPost').val(now);
					var labelName = jQuery('#resourceLabel').find(':selected').text();
					if(labelName=='None'){
						labelName='Unlabeled';
					}
					//now we need to insert the info into the proper spot below.
					var relInsert = time+'_'+jQuery.trim(id)+'_'+subject+'_'+label;
				var insert = '<div class="popcornLMBlockShell popcornLMBlockShell-'+label+'" rel="'+relInsert+'"><div class="popcornLMBlockInner"><div class="popcornLMBlockTitle"><div class="popcornLMBlockTitleInner"><h4>'+title+'</h4><span class="popcornLMBlockTime" rel="'+time+'">'+secondsToMinutesHours(time)+'</span></div><div class="popcornLMBlockLabelLink"><div class="popcornLMBlockEditLinks"><span class="popcornLMBlockExpandText"><a class="popcornLMBlockExpand" href="#" style="margin-right: 5px;">Expand</a> - </span><a class="popcornLMBlockEdit" href="#updateVideoLinkBox">Edit</a> - <a class="popcornLMBlockDelete" href="#">Delete</a></div><p class="popcornLMHiddenLabel">'+labelName+'</p><div style="clear: both;"></div></div></div><div class="popcornLMContentArea">'+text+'</div>';
				
				if(sources.length>0){
					if(sources.length>1){
						insert += '<h6 class="popcornLMSourceListTitle">Sources:</h6>';
					}else{
						insert += '<h6 class="popcornLMSourceListTitle">Source:</h6>';
					}	
				}

				insert += '<ul class="popcornLMSourceList">';
				
				if(response.data.sources!='none'){
					//there are sources to iterate. yay!
					jQuery.each(response.data.sources,function(k,val){
						insert += '<li rel="'+val.id+'">';
						if(val.url!=''){
							insert += '<a href="'+val.url+'" class="popcornLMSourceLink" target="_blank">'+val.title+'</a>';
						}else{
							insert += '<span class="popcornLMSourceLink">'+val.title+'</span>';
						}
						if(val.types!=''){
							insert += '<span class="popcornLMSourceType">'+val.types+'</span>';
						}
						insert += '</li>';
					});
				}
				
				insert += '</ul></div></div>';
			
			
				var column = jQuery('.resourceListDisplayColumn[rel='+subject+']');
				var blockList = new Array();
				var last = 0;
				column.children('.popcornLMBlockShell').each(function(index,block){
					var blockTime = jQuery(this).data('blockTime');
					blockTime = parseInt(blockTime);
					if(last==blockTime){
						blockTime = last+.001;
					}
				
					last = blockTime;
					blockList.push(blockTime);
				});
				var initLength = blockList.length;
				
				if(initLength!=0){
					blockList.push(time);

					//sorts by time (ascending) and then reverses it, so we have the proper spot.
					blockList.sort();
					blockList.reverse();

					//returns index, or key, for where that time landed after the sort.
					var i = jQuery.inArray(time,blockList);
						if(i==initLength){
							column.append(insert);
						}else{

							jQuery(column.children('.popcornLMBlockShell')[i]).before(insert);
						}
				}else{
					column.append(insert);
					var only = 'yes';
				}
				
					
					var inserted = jQuery('.popcornLMBlockShell[rel="'+relInsert+'"]');
					//last but not least, we need to apply the data rules so it can be edited like any other.
					var	subHead = inserted.find('.popcornLMBlockTitle');
						 title = subHead.outerHeight(true);
						//title = title+10;
						//so our images don't overflow.
						inserted.find('img').css({'max-width':'97%','width':'auto','height':'auto'});
						shell = inserted.outerHeight(true);
						inserted.data('fullHeight',shell);
						inserted.data('titleHeight',title);
						inserted.data('blockTime',time);
						inserted.data('blockId',id);
						inserted.data('subjectId',subject);
						inserted.data('labelId',label);
						if(jQuery(this).hasClass('popcornLMLastBlock')||only=='yes'){
							newHeight = inserted.data('fullHeight')-10;
						}else{
							newHeight = inserted.data('titleHeight');
						}



						inserted.addClass('popcornLMOn');	
						inserted.animate({'height': newHeight},400);
					
					
					
					
					
					
					alert('Your entry has been added!');
					
					if (jQuery("#wp-popcorninfobody-wrap").hasClass("tmce-active")){
				         tinyMCE.editors.popcorninfobody.setContent('');
				    }else{
				         jQuery('#popcorninfobody').val('');
				    }
				 jQuery('#resourceTitle').val('');
					
					
				}else{
					if(response.message){
						alert(response.message);
					}else{
						alert('There was a problem. Apologies.');
					}
				}
				
				//alert(response.response);
			//	console.log(response.response);
			},"json");
			
			
			e.preventDefault();
		});
		
		
		function insertBySort(id,insertData){
			var insert = insertData;
			//the values in here need changed but idea is the same.
			var list = new Array();
			jQuery('p.textSort').each(function(i,item){
				list.push(jQuery(this).attr('rel'));
			});
			var initLength = list.length;
			
			list.push(id);
		
			list.sort();
			
			var i = jQuery.inArray(4,list);
			
			if(i==initLength){
				jQuery('#testsortbox').append(insert);
			}else{
				jQuery(jQuery('#testsortbox').children('.textSort')[i]).before(insert);
			}
			
			
		}
		
		
		jQuery('.updateResource').live('click',function(e){
			var time = jQuery('#updateVideoTimeHidden').val();
			var title = jQuery('#updateResourceTitle').val();
			var subject = jQuery('#updateResourceSubject').val();
			var label = jQuery('#updateResourceLabel').val();
			if (jQuery("#wp-popcornupdateinfobody-wrap").hasClass("tmce-active")){
				//popcornupdateinfobody is the ID
			var text =	tinyMCE.editors.popcornupdateinfobody.getContent();
		       
		    }else{
		      var text =  jQuery('#popcornupdateinfobody').val();
		    }
			
			var id = jQuery('#updateVideoId').val();
			var postId = jQuery('#idOfPost').val();
			
			//sourceRow
			var sources = {};
			jQuery('.updateSingleSource').each(function(index,thisElem){
			
				var source = jQuery(this).find('.singleSourceField').val();
				
				if(source!=''){
					var temp = jQuery(this).find('.singleSourceField').attr('name')
					var tempID = temp.split("[")[1].split("]")[0];
					sources[tempID] = source;
				}
	
			});
		

			var data = {
				action : 'popcornlm-update-video-record',
				time : time,
				title : title,
				subject : subject,
				label : label,
				text : text,
				sources : sources,
				id : id,
				postId : postId
			};
			
			
			//okay we are ready to make our ajax call!
			
			jQuery.post(ajaxurl, data, function(response){
			
				if(response.response=='success'){
					//we need to update the time in the box for the original entry.
					var oldTime = jQuery('#videoTimeHidden').data('oldTime');
					if(pop){
						var newTime = parseInt(oldTime);
						pop.currentTime(newTime);
					}
					jQuery('#updateVideoLinkBox').hide();
					jQuery('#addVideoLinkBox').show();
				//	var now = Math.round((new Date()).getTime() / 10);
				//	jQuery('#timeOfPost').val(now);
				
				//last, we need to send the new values into the place of the old ones.
				
				//this nabs our element
				var updateBlock = jQuery('.popcornLMBlockShell[rel="'+response.data.oldRel+'"]');
				
				updateBlock.find('.popcornLMBlockTitleInner h4').html(title);
				updateBlock.find('.popcornLMContentArea').html(text);
				var insert = '';
				var sourceCount = 0;
				
				if(response.data.sources!='none'){
					//there are sources to iterate. yay!
					jQuery.each(response.data.sources,function(k,val){
						insert += '<li rel="'+val.id+'">';
						if(val.url!=''){
							insert += '<a href="'+val.url+'" class="popcornLMSourceLink" target="_blank">'+val.title+'</a>';
						}else{
							insert += '<span class="popcornLMSourceLink">'+val.title+'</span>';
						}
						if(val.types!=''){
							insert += '<span class="popcornLMSourceType">'+val.types+'</span>';
						}
						insert += '</li>';
						sourceCount++;
					});
					alert(insert);
					if(sourceCount>1){
						sourceTitle = 'Sources:';
					}else{
						sourceTitle = 'Source:';
					}
					
					if(updateBlock.find('.popcornLMSourceList').length){
						updateBlock.find('.popcornLMSourceList').html(insert);
						updateBlock.find('.popcornLMSourceListTitle').html(sourceTitle);
					}else{
						updateBlock.find('.popcornLMContent').after('<h6 class="popcornLMSourceListTitle">'+sourceTitle+'</h6><ul class="popcornLMSourceList">'+insert+'</ul>');
					}
					
				}else{
					//erase what was there
					updateBlock.find('.popcornLMSourceList').detach();
					updateBlock.find('.popcornLMSourceListTitle').detach();
				}
				
					alert('Your entry has been updated! Changes will show up here after your next refresh.');
				}else{
				//	alert(response.problem);
				}
				
				//alert(response.response);
			//	console.log(response.response);
			},"json");
			
			
			e.preventDefault();
		});
		
			function get_tinymce_content(){
			    if (jQuery("#wp-popcorninfobody-wrap").hasClass("tmce-active")){
			        return tinyMCE.editors.popcorninfobody.getContent();
			    }else{
			        return jQuery('#popcorninfobody').val();
			    }
			}
		
		
		

		
	});



</script>


<?php

	}
	
	
	public function showPopcornBox($post) {
		
		
		$custom_meta_fields = $this->custom_meta_fields;
	
	// Use nonce for verification
	echo '<input type="hidden" name="custom_meta_box_nonce" value="'.wp_create_nonce(basename(__FILE__)).'" />';
		?>
		<input type="hidden" name="idOfPost" id="idOfPost" value="<?php echo $post->ID; ?>" />
		<div id="adminPopcornWrapper">
		<div id="shortcodeDisplay" style="display: none;"></div>
		<div id="adminPopcorn" ></div>
		<?php
		
		
		
		//video is unique
		$videoMeta = get_post_meta($post->ID, $this->prefix.'youtube', true);
		
		
		
			
			echo '<div id="youtubeLinkField" >';
			echo '<h2>Step 1: Choose a Video</h2>';
			echo '<label for="'.$this->prefix.'youtube'.'">'.'YouTube Link: '.'</label>
				<input type="text" name="'.$this->prefix.'youtube'.'" id="'.$this->prefix.'youtube'.'" value="'.$videoMeta.'" size="30" />
				<br /><span class="description">Paste in the URL for the YouTube video. Example: http://youtube.com/watch?v=SOMEID</span><br /><br /><input class="button-primary" type="submit" name="save" value="Link to Video" id="videoSubmit"><span id="videoCancel"></span></div><div id="youtubeActiveLink"></div>';
		
		?>
		</div>
		
		<div id="initOptions" style="display: none;">
		<?php $optionsMeta = get_post_meta($post->ID,'popcornLMOptions',true);
			if($optionsMeta){
				$optionsObject = json_decode($optionsMeta);
				$subjects = get_object_vars($optionsObject->subjects);
				$vidOutcomeTemplate = $optionsObject->vidOutcomeTemplate;
				if($subjects!=''&&$vidOutcomeTemplate!=''){
					//everything is set up, proceed as planned.
					?>
					<script type="text/javascript">
					jQuery(document).ready(function(){
					//	jQuery('#addVideoLinkBox').html('').hide();
					//	jQuery('#initOptions').show();
					jQuery('.configLinks').append(' | <a href="#" class="changeConfig">Change Configuration</a>');
					jQuery('#shortcodeDisplay').html('<?php echo 'To display the video/resources, paste the following shortcode into the main text box for the page you want it to show up on:<br /> [popcornLM id='.$post->ID.'] ';?>').show();
					});
						</script>
					<?php
				
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
			}else{
				if($videoMeta&&$videoMeta!=''){
					?>
					<script type="text/javascript">
					jQuery(document).ready(function(){
						jQuery('#addVideoLinkBox').hide();
						jQuery('#initOptions').show();
					});
					</script>
					<?php
				}else{
					?>
					<script type="text/javascript">
					jQuery(document).ready(function(){
						jQuery('#addVideoLinkBox').hide();
					//	jQuery('#initOptions').show();
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
					//print_r($labelMeta);
					foreach($labelMeta['colors'] as $id=>$colorData){
						
						echo '<div class="templatePreviewColInstance"><div class="templatePreviewColName">'.$colorData['label'].'</div><div class="templatePreviewColSwatch" style="background-color: #'.$colorData['col'].'; "></div></div>';
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
						?>
						<div class="singleSubject">
						<a class="subjectAdd button" style="display: none;" href="#">Add <br />Speaker/Topic</a>
						<div class="singleSubjectInput" style="display: none;">
						<small>Type and choose from the list that appears.</small>
						<input type="text" name="singleSubject[<?php echo $id; ?>]" class="singleSubjectField"  onkeypress="if(event.keyCode==13) return false;" value="<?php echo $val; ?>" size="10" />	<br /><a class="subjectCancelAdd" href="#">Cancel</a></div>
						<div class="singleSubjectPreview">
						<?php
						$subjectTitle = get_the_title($val);
						$subjectThumb = get_post_meta($val,'subjectThumb',true);
						if($subjectThumb&&$subjectThumb!=''){
							$subjectImage = wp_get_attachment_image_src($subjectThumb, 'popcornlm-subject-thumb');
							$subjectImage = $subjectImage[0];
							echo '<img class="singleSubjectPreviewImg" src="'.$subjectImage.'" />';
							echo '<p style="text-align: center; font-weight: bold;">';
						}else{
							echo '<p style="text-align: center; font-weight: bold; margin-top: 50px;">';
						}
						echo $subjectTitle.'<br /><a href="#" class="subjectRemove">(remove)</a></p>';
						?>
	
						
						</div>
						</div>

						
						<?php
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
		
		<div id="updateVideoLinkBox" style="display: none;">
		<input type="hidden" name="updateVideoTime" id="updateVideoTimeHidden" value="0" />
		
		<input type="hidden" name="updateVideoId" id="updateVideoId" value=""/>
	
		
		<h2>Update Video Link</h2>
		<p>Update the fields and click "Update Video Link." All but sources are mandatory, but at least one source should be included for credibility.</p><br />
		<div class="resourceInfoContainer">
		<div class="resourceInfo"><p>Time: <span class="videoTime"></span></p>
		<p style="margin-top: 21px;">Title: <input type="text" name="updateResourceTitle" id="updateResourceTitle" /></p>
		</div>
		<div class="resourceInfo"><label for="updateResourceSubject">Person/Topic: </label>
		<select name="updateResourceSubject" id="updateResourceSubject">
		<?php
		if($subjects){
			foreach($subjects as $k=>$v){
				$subjectTitle = get_the_title($v);
				echo '<option value="'.$v.'">'.$subjectTitle.'</option>';
			}
		}
		?>
		
		</select><br /><br />
		<label for="updateResourceLabel">Label: </label>
		<select name="updateResourceLabel" id="updateResourceLabel">
		<option value="none">None</option>
		<?php
		if($labelMeta){
			foreach($labelMeta['colors'] as $id=>$info){
				echo '<option value="'.$id.'" style="color: #'.$info['col'].';">'.$info['label'].'</option>';
			}
		}
		?>
		</select>
		</div>
		
		<div style="clear: both;"></div>
		
		
		<br /><h2>Justification of Label:</h2>
		<?php
		$args = array(
			'textarea_rows'=>4
		
			
		);
	
		
		wp_editor('','popcornupdateinfobody',$args);
		
	
		 ?><br />
		<h2>Sources:</h2>
		<p>Search for your source from the site database. If your source is new to this site, <a href="post-new.php?post_type=popcornlm_sources" target="_blank">add it here</a> (new window), then return to this form and connect to it.</p>
		<div id="updateSourceTable">
	
		</div>
		<div class="updateSingleSource">
		<a class="sourceAdd button" href="#">Connect to Source</a>
		<div class="singleSourceInput" style="display: none;">
		<small>Type and choose from the list that appears.</small>
		<input type="text" name="singleSource[<?php echo time(); ?>]" class="singleSourceField"  onkeypress="if(event.keyCode==13) return false;" value="" size="10" />	<br /><a class="sourceCancelAdd" href="#">Cancel</a></div>
		<div class="singleSourcePreview" style="display: none;"></div>
		</div>
			
		<p style="text-align: right;"><a style="color: red; padding-right: 10px;" href="#" class="cancelUpdate">Cancel</a> <a class="button-primary updateResource" href="#" >Update Record</a></p>
		
		
		</div><!-- end of resourceInfoContainer-->
		</div>
		
		
		<div id="addVideoLinkBox">
		<input type="hidden" name="videoTime" id="videoTimeHidden" value="0" />
		
		<input type="hidden" name="timeOfPost" id="timeOfPost" value="<?php echo time();?> "/>
	
		
		<h2>Step 3,4,5...: Add a Video Link</h2>
		<p>Scrub the player until the time you want shows up below, then fill out the fields and click "Add Video Link." All but sources are mandatory, but at least one source should be included for credibility.</p><br />
		<div class="resourceInfoContainer">
		<div class="resourceInfo"><p>Time: <span class="videoTime"></span></p>
		<p style="margin-top: 21px;">Title: <input type="text" name="resourceTitle" id="resourceTitle" /></p>
		</div>
		<div class="resourceInfo"><label for="resourceSubject">Person/Topic: </label>
		<select name="resourceSubject" id="resourceSubject">
		<?php
		if($subjects){
			foreach($subjects as $k=>$v){
				$subjectTitle = get_the_title($v);
				echo '<option value="'.$v.'">'.$subjectTitle.'</option>';
			}
		}
		?>
		
		</select><br /><br />
		<label for="resourceLabel">Label: </label>
		<select name="resourceLabel" id="resourceLabel">
		<option value="none">None</option>
		<?php
		if($labelMeta){
			foreach($labelMeta['colors'] as $id=>$info){
				echo '<option value="'.$id.'" style="color: #'.$info['col'].';">'.$info['label'].'</option>';
			}
		}
		?>
		</select>
		</div>
		
		<div style="clear: both;"></div>
		
		
		<br /><h2>Justification of Label:</h2>
		<?php
		$args1 = array(
			'textarea_rows'=>4,
		);
	
		
		wp_editor('','popcorninfobody',$args1);
		
	
		 ?><br />
		<h2>Sources:</h2>
		<p>Search for your source from the site database. If your source is new to this site, <a href="post-new.php?post_type=popcornlm_sources" target="_blank">add it here</a> (new window), then return to this form and connect to it.</p>
		
			<div class="sourceTable">
			<div class="singleSource">
			<a class="sourceAdd button" href="#">Connect to Source</a>
			<div class="singleSourceInput" style="display: none;">
			<small>Type and choose from the list that appears.</small>
			<input type="text" name="singleSource[<?php echo time(); ?>]" class="singleSourceField"  onkeypress="if(event.keyCode==13) return false;" value="" size="10" />	<br /><a class="sourceCancelAdd" href="#">Cancel</a></div>
			<div class="singleSourcePreview" style="display: none;"></div>
			</div>
			
			</div>
			
			
<div style="clear: both;"></div>
		<p style="text-align: right;"><a class="button-primary submitResource" href="#" >Submit Record</a></p>
		
		
		</div><!-- end of resourceInfoContainer-->
		</div>
		
		<div id="resourceListContainer">
		<h2 style="text-align: center;">Video List Preview</h2>
		<?php
		//order is not 100% accurate on add block if add block is within two seconds or so of the other source. Not a common problem.
		$resourceBlocks = get_post_meta($post->ID,'resourceBlock');
		
		if($resourceBlocks&&is_array($resourceBlocks)){
			
			$sortedBlocks = array();
			
			foreach($resourceBlocks as $resourceBlock){
				//first we need to parse out how many columns. Then the labels for each column. Then organize the resources and post according to time. Sheesh!
				if($labelMeta&&$subjects){
					//everything we need to sort and display.
					//first, by subject
					foreach($subjects as $id=>$val){
						if($resourceBlock['subject']==$val){
							//this array is a match. We need to sort them out now.
							
							//add it to the subjects array, then by time (so we can sort), then by id in case there are two at the same time.
							$sortedBlocks[$val][$resourceBlock['time']][$resourceBlock['id']] = $resourceBlock;
						}
						
						
						
					}
		
				}
				
			
			}
			
			
			
			
				$columns = count($subjects);
				echo '<style>.resourceListDisplayColumn{width: '.(92/$columns).'%; margin-left: '.(1/$columns).'%; margin-right: '.(1/$columns).'%; float: left; padding: '.(2/$columns).'%;  }';
				foreach($labelMeta['colors'] as $id=>$label){
					echo '.popcornLMBlockShell-'.$id.'{background-color:#'.$label['col'].';}';
				}
				echo '</style>';
			
			
				foreach($subjects as $id=>$val){
					//this sorts them by time. They are now separated by name and sorted by time. We can display!
					krsort($sortedBlocks[$val]);
					echo '<div class="resourceListDisplayColumn" rel="'.$val.'">';
					
					
					echo '<div class="resourceListDisplayHead">';
				
					echo '<div class="popcornLMDisplayHeadText">';
					echo '<h4 class="popcornLMDisplayHeadTitle">'.get_the_title($val).'</h4>';
					
					echo '</div>';
					echo '</div><div style="clear: both;"></div>';
					
					
					foreach($sortedBlocks[$val] as $time=>$id){
						//another foreach in case there are two blocks with the same time
						foreach($id as $blockId=>$blockInfo){
							if($blockInfo['title']!=''&&$blockInfo['text']!=''){

								echo '<div class="popcornLMBlockShell popcornLMBlockShell-'.$blockInfo['label'].'" rel="'.$blockInfo['time'].'_'.trim($blockInfo['id']).'_'.$blockInfo['subject'].'_'.$blockInfo['label'].'">';
								echo '<div class="popcornLMBlockInner">';
								echo '<div class="popcornLMBlockTitle">';
								echo '<div class="popcornLMBlockTitleInner">';
								echo '<h4>'.$blockInfo['title'].'</h4>';
								echo '<span class="popcornLMBlockTime" rel="'.$blockInfo['time'].'">'.gmdate('H:i:s',$blockInfo['time']).'</span></div>';

								echo '<div class="popcornLMBlockLabelLink"><div class="popcornLMBlockEditLinks"><span class="popcornLMBlockExpandText"><a class="popcornLMBlockExpand" href="#" style="margin-right: 5px;">Expand</a>';
								echo ' - </span><a class="popcornLMBlockEdit" href="#updateVideoLinkBox">Edit</a> - ';
								echo '<a class="popcornLMBlockDelete" href="#">Delete</a></div>';


								if(isset($labelMeta['colors'][$blockInfo['label']]['label'])){
									echo '<p class="popcornLMHiddenLabel">';

									echo $labelMeta['colors'][$blockInfo['label']]['label'].'</p>';
									echo '<div style="clear: both;"></div>';
								}else{
									echo '<p class="popcornLMHiddenLabel">Unlabeled</p>';
									echo '<div style="clear: both;"></div>';
								}

								echo '</div></div><div class="popcornLMContentArea">';
								
								echo html_entity_decode($blockInfo['text']);
								echo '</div>';
								if(isset($blockInfo['sources'])&&is_array($blockInfo['sources'])){
									$sources = count($blockInfo['sources']);
									if($sources>1){
										$sourceHead = 'Sources';
									}else{
										$sourceHead = 'Source';
									}


									echo '<h6 class="popcornLMSourceListTitle">'.$sourceHead.':</h6>';
									echo '<ul class="popcornLMSourceList">';
									foreach($blockInfo['sources'] as $id=>$sourceId){
										if(is_numeric($sourceId)){
											$name = get_the_title($sourceId);
											$url = get_post_meta($sourceId,'sourceUrl',true);
											
											echo '<li rel="'.$sourceId.'">';
											if($url!=''){
												echo '<a href="'.$url.'" class="popcornLMSourceLink" target="_blank">'.$name.'</a>';
											}else{
												echo '<span class="popcornLMSourceLink">'.$name.'</span>';
											}
											$types = wp_get_object_terms($sourceId,'source_types');
											if(!empty($types)){
												$counter = 0;
												//counter so we only put comma after first one, not before
												echo '<span class="popcornLMSourceType">';
												foreach($types as $type){
													if($counter>0){
														echo ', ';
													}
													echo $type->name;
													$counter++;
												}
												echo '</span>';
											}
											

											echo '</li>';
										}
										
									}
									echo '</ul>';

								}


								echo '</div></div>';
							}	else{
									//this would happen if there were no title or text. shouldn't happen.
									//echo 'wompsticks';
								}

						}


					}

					echo '</div>';
				}
			
			
			//we need to sort the times so lowest are first.
			
		
			//print_r($sortedBlocks);
			
		}else{
			$columns = count($subjects);
			echo '<style>.resourceListDisplayColumn{width: '.(92/$columns).'%; margin-left: '.(1/$columns).'%; margin-right: '.(1/$columns).'%; float: left; padding: '.(2/$columns).'%;  }';
			foreach($labelMeta['colors'] as $id=>$label){
				echo '.popcornLMBlockShell-'.$id.'{background-color:#'.$label['col'].';}';
			}
			echo '</style>';
		
		
			foreach($subjects as $id=>$val){
				//this sorts them by time. They are now separated by name and sorted by time. We can display!
				krsort($sortedBlocks[$val]);
				echo '<div class="resourceListDisplayColumn" rel="'.$val.'">';
				
				
				echo '<div class="resourceListDisplayHead">';
			
				echo '<div class="popcornLMDisplayHeadText">';
				echo '<h4 class="popcornLMDisplayHeadTitle">'.get_the_title($val).'</h4>';
				
				echo '</div>';
				echo '</div><div style="clear: both;"></div></div>';
			}
		}
		
		?>
		<div style="clear: both;"></div>
		
		</div>
		
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

		}elseif("popcornlm_sources"==$_POST['post_type']){
			$sourceArray = array();
			$sourceArray['popcornsourceabstract'] = esc_attr($_POST['popcornsourceabstract']);
		
			$sourceArray['sourceUrl'] = esc_attr($_POST['sourceUrl']);

			foreach($sourceArray as $k=>$v){
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