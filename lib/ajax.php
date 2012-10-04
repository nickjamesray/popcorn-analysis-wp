<?php



//Ajax calls.
class PopcornLM_Ajax {
	
	
	public function __construct(){
		
		//ajax queries
		add_action('wp_ajax_popcornlm-subject-list', array($this, 'SubjectList'));
		add_action('wp_ajax_popcornlm-single-subject', array($this, 'SingleSubject'));
		add_action('wp_ajax_popcornlm-template-meta', array($this, 'templateMeta'));
		add_action('wp_ajax_popcornlm-create-video-record', array($this,'createVideoRecord'));
		add_action('wp_ajax_popcornlm-update-video-record', array($this,'updateVideoRecord'));
		add_action('wp_ajax_popcornlm-delete-video-record', array($this,'deleteVideoRecord'));
		
		add_action('wp_ajax_popcornlm-source-list', array($this, 'SourceList'));
		add_action('wp_ajax_popcornlm-single-source', array($this, 'SingleSource'));
	}
	
	
	public function createVideoRecord(){
		$array = array();
		if($_POST['time']!=''&&$_POST['subject']!=''&&$_POST['label']!=''&&$_POST['id']!=''&&$_POST['title']!=''&&$_POST['text']!=''){
			//we have the essentials, let's add to database.
			$entry = array();
			$entry['id'] = $_POST['id'];
			
			$entry['time'] = $_POST['time'];
			$entry['subject'] = esc_attr($_POST['subject']);
			$entry['label'] = esc_attr($_POST['label']);
			$entry['title'] = esc_attr($_POST['title']);
			$entry['text'] = esc_attr($_POST['text']);
			$entry['sources'] = $_POST['sources'];
			
			if(is_numeric($_POST['postId'])){
				add_post_meta($_POST['postId'],'resourceBlock',$entry);
			}
			//to make sure this record now exists, we search for it by id
			$args = array(
				'post_type'=>'popcornlm',
				'meta_query'=>array(
					array(
						'key'=>'resourceBlock',
						'value'=>$_POST['id'],
						'compare'=>'LIKE'
					)
					
				)
			);
			$query = new WP_Query($args);
			
			if($query&&$query->found_posts>0){
				//it was added, now we can return success so jquery can insert that data onto the page where needed.
				$array['response'] = 'success';
				//$custom = get_post_meta($_POST['label'],'labelVals',true);
				if(!empty($entry['sources'])){
					$counter = 0;
					foreach($entry['sources'] as $sourceId){
						$array['data']['sources'][$counter]['id'] = $sourceId;
						$array['data']['sources'][$counter]['title'] = get_the_title($sourceId);
						$array['data']['sources'][$counter]['url'] = get_post_meta($sourceId,'sourceUrl',true);
						$types = wp_get_object_terms($sourceId,'source_types');
						$array['data']['sources'][$counter]['types'] = '';
						if(!empty($types)){
							$count = count($types);
							foreach($types as $type){
								$array['data']['sources'][$counter]['types'] .= $type->name;
								if($count>1){
									$array['data']['sources'][$counter]['types'] .= ', ';
								}
								$count--;
							}
						}
						$counter++;
					}
				}else{
					$array['data']['sources'] = 'none';
				}
			
				//$array['data']['labelName'] = get_the_title($_POST['label']); 
			}else{
				//not added for some reason
				$array['response'] = 'fail';
				
			}

		//	$array['response'] = $entry;
		}else{
			$array['response'] = 'fail';
			$array['message'] = 'Not all required fields were entered. Please try again.';
		}
		echo json_encode($array);
		die();
	}
	
	public function updateVideoRecord(){
		global $wpdb;
		$array = array();
		if($_POST['time']!=''&&$_POST['subject']!=''&&$_POST['label']!=''&&$_POST['id']!=''&&$_POST['title']!=''&&$_POST['text']!=''){
			//we have the essentials, let's find and update to database.
			$entry = array();
			$entry['id'] = $_POST['id'];
			///wtf with the escaping!
			$entry['time'] = $_POST['time'];
			$entry['subject'] = esc_attr($_POST['subject']);
			$entry['label'] = esc_attr($_POST['label']);
			$entry['title'] = esc_attr($_POST['title']);
			$entry['text'] = esc_textarea($_POST['text']);
			$entry['sources'] = $_POST['sources'];

			//now we find to make sure this exists. If for some reason it does not, we can create the record instead.
			$id = $entry['id'];
			$existCheck = $wpdb->get_row("SELECT * FROM $wpdb->postmeta WHERE meta_key = 'resourceBlock' AND meta_value LIKE '%$id%'",ARRAY_A);
			if($existCheck){
				//it exists, now we just need to update it.
				
				if(is_numeric($_POST['postId'])){
					$postId = $_POST['postId'];
					$old = unserialize($existCheck['meta_value']);
					$update = update_post_meta($postId,'resourceBlock',$entry,$old);
					if($update||$entry==$old){
						//either the update happened or there were no changes
						$array['response'] = 'success';
						$array['message'] = 'Your entry has been updated!';
					}else{
						$array['response'] = 'fail';
						$array['message'] = 'There was a system error. Please try again.';
					}
					
			}
				
				
				//$array['response'] = serialize($entry);
			}else{
				//it does not yet exist. This shouldn't happen because clicking the update will give it an ID. Bad news if its mistaken.
				
			}
			

			//$wpdb->update();

		}else{
			$array['response'] = 'fail';
	
			$array['problem'] = 'Not all required fields were entered. Please try again.';
		}
		echo json_encode($array);


		die();
	}
	
	
	public function deleteVideoRecord(){
		global $wpdb;
		$array = array();
		
		if($_POST['entryId']&&$_POST['postId']){
			$entryId = $_POST['entryId'];
			$postId = $_POST['postId'];
			$delete = $wpdb->query("DELETE FROM $wpdb->postmeta WHERE post_id = $postId AND meta_key = 'resourceBlock'  AND meta_value LIKE '%$entryId%' LIMIT 1");
			if($delete){
				$array['response'] = 'success';
			}else{
				$array['response'] = 'fail';
			}
			
		}
		
		echo json_encode($array);
		die();
	}
	
	public function SubjectList(){
		$q = strtolower($_REQUEST["q"]);
		if (!$q) return;
		$args = array(
					'post_type'=>'popcornlm_subjects',
					'posts_per_page'=>-1,
					'orderby'=>'title',
					'order'=>'ASC'
				);
				$loop = new WP_Query($args);
				while ($loop->have_posts()) : $loop->the_post();
				$title = get_the_title();
				$titleCheck = strtolower($title);
				if(strpos($titleCheck,$q)!==false){
					echo get_the_title();
					echo "\n";
				}
	
				endwhile;
	
		die();
	}
	
	public function SourceList(){
		$q = strtolower($_REQUEST["q"]);
		if (!$q) return;
		$args = array(
					'post_type'=>'popcornlm_sources',
					'posts_per_page'=>-1,
					'orderby'=>'title',
					'order'=>'ASC'
				);
				$loop = new WP_Query($args);
				while ($loop->have_posts()) : $loop->the_post();
				 $title = get_the_title();
				$titleCheck = strtolower($title);
				if(strpos($titleCheck,$q)!==false){
					echo get_the_title();
					echo "\n";
				}
				
				endwhile;
	
		die();
	}
	
	
	public function SingleSubject(){
		
		$array = array();
		$subject = get_page_by_title($_GET['subject'],ARRAY_A,'popcornlm_subjects');
		
		$array['title'] = $_GET['subject'];
		$array['id'] = $subject['ID'];
		$subjectMeta = get_post_custom($subject['ID']);
		if(!empty($subjectMeta['subjectThumb'])){
			 $image = wp_get_attachment_image_src($subjectMeta['subjectThumb'][0], 'popcornlm-subject-thumb');
			$array['image'] = $image[0];
		}
		if(!empty($subjectMeta['subjectSubhead'])){
			$array['subhead'] = $subjectMeta['subjectSubhead'][0];
		}
		//eventually we will include the text body here, but not right now.
		
		
		
		echo json_encode(array('data'=>$array));
		die();
	}
	
	public function SingleSource(){
		
		$array = array();
		$source = get_page_by_title($_GET['source'],ARRAY_A,'popcornlm_sources');
		
		$array['title'] = $_GET['source'];
		$array['id'] = $source['ID'];
	//	$sourceMeta = get_post_custom($source['ID']);
		
		// if(!empty($sourceMeta['subjectSubhead'])){
		// 		$array['subhead'] = $subjectMeta['subjectSubhead'][0];
		// 	}
		//eventually we will include the text body and source type here, but not right now.
		
		
		
		echo json_encode(array('data'=>$array));
		die();
	}
	
	public function templateMeta(){
		
		$array = array();
		if($_GET['id']!=='default'){
			$templateMeta = get_post_custom($_GET['id']);
			$array['name'] = get_the_title($_GET['id']);
			$labelMetaVals = str_replace("\\","",$templateMeta['labelVals'][0]);
			$array['labelVals'] = json_decode($labelMetaVals);
		}else{
		
			$array['name'] = 'Default True/Uncertain/False';
			$array['labelVals'][0]['label'] = "True";
			$array['labelVals'][0]['col'] = "33BB00";
			$array['labelVals'][1]['label'] = "Uncertain";
			$array['labelVals'][1]['col'] = "444444";
			$array['labelVals'][2]['label'] = "False";
			$array['labelVals'][2]['col'] = "CC1100";
			
		}
		
		
		
		
		echo json_encode(array('data'=>$array));
		
		die();
	}
	
}


?>