<?php
add_action('action_hook_espresso_registration_form_bottom','create_or_join_team');
//add_action('action_hook_espresso_save_attendee_meta','save_cj_team_data');
//add_action('action_hook_espresso_save_attendee_meta','get_attendee');
if(!function_exists('create_or_join_team')):
function create_or_join_team()
{
	$digit1 = mt_rand(1,20);
	$digit2 = mt_rand(1,20);
if( mt_rand(0,1) === 1 ) {
    $math = "$digit1 + $digit2";
    $_SESSION['answer'] = $digit1 + $digit2;
} else {
    $math = "$digit1 - $digit2";
    $_SESSION['answer'] = $digit1 - $digit2;
}
	?>
              
               <script>
		var $jt=jQuery.noConflict();
                $jt(document).ready(function(){	
				$jt(".espresso_add_subtract_attendees").before("<div id='join_team_main' style='border:1px solid;margin-top:10px'><div style='padding:10px;' id='load_team'> Do you want to Join/Create a Team ?  Yes <input type='radio' id='join_yes' name='join_team' value='yes'/> No <input type='radio' id='join_no' name='join_team' value='no' /></div><img id='loader' style='display:none;' src='<?php echo plugins_url( 'loader.gif', __FILE__ ); ?>'></img></div>");
					$jt('#join_yes').click(function(){
					$jt('#loader').show();
					var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
						//$jt('#join_div').show(100);
						 // This does the ajax request
							$jt.ajax({
								url: ajaxurl,
								data: {
									'action':'Load_team_data'            
								},
								success:function(response) {
								$jt('#loader').hide();
								$jt('#load_team').after(response);
								//alert(response);
									// This outputs the result of the ajax request
									//console.log(data);
								},
								error: function(errorThrown){
									console.log(errorThrown);
								}
							});  
						});
					$jt('#join_no').click(function(){
						$jt('#join_div').hide();
						});	
						
					});
					function loadnewteambox(val)
					{
					  if(val=='create_team')
					  {
					   document.getElementById("new_create_team").style.display='block';
					   }
					   else
					   {
					   document.getElementById("new_create_team").style.display='none';
					   }
					}
					function loadnewname()
					{
					 var $new_team_name = document.getElementById('team_name').value;
					 var url = "<?php echo site_url(); ?>/wp-content/plugins/personal-fundraiser/includes/validate-captcha.php";
					 var captcha = $jt("#pfund-captcha").val();
						 
					 if($new_team_name=='')
						  {
							  alert('Please Enter team name!');
						  }
						 else 
						  {
							var ntn='<option value="'+$new_team_name+'" selected="selected">'+$new_team_name+'</option>'; 
							$jt.ajax({
								url: url,
								data: { captcha:captcha},
								type: "post", 
								success:function(response) {
								if(response == "wrong"){
								alert("Invalid Captcha!");
								return false;
								}else{
							$jt('#join_teams').append(ntn);
							$jt('#new_create_team').hide();
									}
																
								},
								error: function(errorThrown){
									console.log(errorThrown);
								}
							}); 
							
						  }
					   
					}
                </script>
				<?php
}
endif;

add_action('wp_head','edit_your_team');
//add_action('action_hook_espresso_save_attendee_meta','save_cj_team_data');
//add_action('action_hook_espresso_save_attendee_meta','get_attendee');
if(!function_exists('edit_your_team')):
function edit_your_team()
{
	if($_GET['edit_attendee'] == true):
	global $wpdb;
;
	$member_id = $_GET['id'];
	$query="SELECT post_id FROM ".$wpdb->prefix."postmeta WHERE `meta_key` = 'team_members' AND `meta_value` LIKE '%".$member_id."%'";
    $result=$wpdb->get_row($query);	
	$post_id=$result->post_id;
	?>
              
               <script>
				var $jt=jQuery.noConflict();
                $jt(document).ready(function(){	
				$jt(".event_form_submit").before("<h3 class='section-title'>Team Information</h3><div id='join_team_main' style='margin-top:10px'><div style='padding:10px;' id='load_team'></div><img id='loader' style='display:none;' src='<?php echo plugins_url( 'loader.gif', __FILE__ ); ?>'></img><input type='hidden' name='current_team' value='<?php echo $post_id;?>'></div>");
					//$jt('#join_yes').click(function(){
					$jt('#loader').show();
					var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
						//$jt('#join_div').show(100);
						 // This does the ajax request
							$jt.ajax({
								url: ajaxurl,
								data: {
									'action':'Edit_Load_team_data',
                                    'post_id':<?php echo $post_id;?>,
									'member_id':<?php echo $member_id;?>
								},
								success:function(response) {
								$jt('#loader').hide();
								$jt('#load_team').after(response);
								//alert(response);
									// This outputs the result of the ajax request
									//console.log(data);
								},
								error: function(errorThrown){
									console.log(errorThrown);
								}
							});  
						//});
					$jt('#join_no').click(function(){
						$jt('#join_div').hide();
						});	
						
					});
					function loadnewteambox(val)
					{
					  if(val=='create_team')
					  {
					   document.getElementById("new_create_team").style.display='block';
					   }
					   else
					   {
					   document.getElementById("new_create_team").style.display='none';
					   }
					}
					function loadnewname()
					{
					 var $new_team_name = document.getElementById('team_name').value;
					 if($new_team_name=='')
						  {
							 alert('Please Enter team name!');
							
						  }
						 else 
						  {
							var ntn='<option value="'+$new_team_name+'" selected="selected">'+$new_team_name+'</option>'; 
							$jt('#join_teams').append(ntn);
							$jt('#new_create_team').hide();
						  }
					   
					}
                </script>
				<?php
				endif;
}
endif;
?>
