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
		
		$subject = get_page_by_title($_GET['subject'],ARRAY_A,'popcornlm_subjects');
	
		echo json_encode(array('data'=>$subject['ID']));
		
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