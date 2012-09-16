<?php



//Ajax calls.
class PopcornLM_Ajax {
	
	
	public function __construct(){
		
		//ajax queries
		add_action('wp_ajax_popcornlm-subject-list', array($this, 'SubjectList'));
		add_action('wp_ajax_popcornlm-single-subject', array($this, 'SingleSubject'));
		add_action('wp_ajax_popcornlm-template-meta', array($this, 'templateMeta'));
	}
	
	
	public function SubjectList(){
		
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