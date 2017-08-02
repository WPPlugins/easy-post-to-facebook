<?php
/*
Plugin Name: Easy post to Facebook
Plugin URI: http://feelsen.com/facebook-post-wordpress-plugin
Description: A simple and powerfull plugin to automatically send your posts to your Facebook Profile or Page
Version: 1.0.2
Author: Sérgio Vilar
Author URI: http://about.me/vilar
License: GPL
*/

include('class/facebook.php');
define(PTF_URL,get_bloginfo('url').'/wp-admin/options-general.php?page=post-to-facebook');
// =================================================================================================== Facebook Functions 

function createConsumerFB(){

            return new Facebook(array(
                'appId'=>'413491268666975',
                'secret'=>'3d3b7d765a10e3e950ba16c18eca6d74',
                'cookie'=>true
            ));
  }

function add_account(){
unset($session['access_token']);

            $facebook = createConsumerFB();
            $session = $facebook->getSession();
            $login_url = $facebook->getLoginUrl(array(
              'next' => PTF_URL.'&feedback=true',
              'cancel_url' => PTF_URL,
              'req_perms' => 'publish_stream,offline_access,user_status,manage_pages'
            ));
        
            if(!isset($_GET['feedback'])): header('Location:'.$login_url); endif;
}

function facebookcallback(){

            $facebook = createConsumerFB();
            $session = $facebook->getSession();
            $user = json_decode(file_get_contents('https://graph.facebook.com/me?access_token='.$session['access_token']));
            $pages = json_decode(file_get_contents("https://graph.facebook.com/".$user->id."/accounts/?access_token=".$session['access_token']));

            global $wpdb;
            $query_check = $wpdb->get_results("SELECT userid FROM `".$wpdb->prefix."postofacebook` WHERE userid = '".$user->id."' AND ds = '".$user->name."'");
            
            if(count($query_check)==0):
                $query_user = "INSERT INTO `".$wpdb->prefix."postofacebook` (`id`, `ds`, `token`, `type`, `active`, `userid`) VALUES (NULL, '".$user->name."', '".$session['access_token']."', 'user', '0', '".$user->id."');";
            else:
                $query_user = "UPDATE `".$wpdb->prefix."postofacebook` SET token =  '".$session['access_token']."' WHERE userid = '".$user->id."';";
            endif;

            $wpdb->query($query_user);
    

          foreach($pages->data as $pagina){

            if($pagina->category == "Application"):
              $type = "app";
            else:
              $type = "page";
            endif;

            $query_checkpage = $wpdb->get_var("SELECT userid FROM `".$wpdb->prefix."postofacebook` WHERE userid = '".$pagina->id."' AND ds = '".$pagina->name."'");

            if(empty($query_checkpage)):
                $query_page = "INSERT INTO `".$wpdb->prefix."postofacebook` (`id`, `ds`, `token`, `type`, `active`, `userid`) VALUES (NULL, '".$pagina->name."', '".$pagina->access_token."', '".$type."', '0', '".$pagina->id."');";
            else:
                $query_page = "UPDATE `".$wpdb->prefix."postofacebook` SET token =  '".$pagina->access_token."' WHERE userid = '".$pagina->id."';";
            endif;

            $wpdb->query($query_page);
            
          }

      }

function publish_to_fb($in,$data){
// Publica uma mensagem no mural do usuário ou fan page com cURL
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, 'https://graph.facebook.com/'.$in.'/feed');
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
  $output = curl_exec($ch);
  $info = curl_getinfo($ch);
  curl_close($ch);
}

function send_post($post_id, $post){
global $wpdb;

    $marcados = $wpdb->get_results("SELECT token,userid FROM ".$wpdb->prefix."postofacebook WHERE active = '1'");
    foreach($marcados as $marcado):
        $args['access_token'] = $marcado->token;
        $args['link'] = get_permalink($post_id);
        publish_to_fb($marcado->userid,$args);
    endforeach;
}

if($_GET['addaccount']==true) add_account();
if($_GET['feedback']==true): facebookcallback(); $msg = "Account added"; endif; 

// =================================================================================================== Wordpress Functions 

function ptf_create_table(){

  global $wpdb;
 
  if($wpdb->get_var("show tables like ".$wpdb->prefix."postofacebook`") != $wpdb->prefix."postofacebook`"){
    $sql = "DROP TABLE `".$wpdb->prefix."postofacebook`;";

    $sql2 =  "CREATE TABLE  `".$wpdb->prefix."postofacebook` (
            `id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY ,
            `ds` VARCHAR( 240 ) NOT NULL ,
            `token` TEXT NOT NULL ,
            `type` VARCHAR( 120 ) NOT NULL ,
            `active` INT NOT NULL,
            `userid` TEXT NOT NULL
            ) ENGINE = MYISAM ;";

    $wpdb->query($sql);
    $wpdb->query($sql2);
  }
}

function ptf_menu() {
  add_options_page('Post to Facebook', 'Post to Facebook', 'manage_options', 'post-to-facebook', 'ptf_options');
}

function ptf_options() {

  global $msg;
  global $wpdb;

    $hidden_field_name = 'ptf_submit';

  // Se o usuário tiver submetido alguma informação
  if(isset($_POST[ $hidden_field_name ]) && $_POST[ $hidden_field_name ] == 'Y' ) {
      $query_all = "UPDATE `".$wpdb->prefix."postofacebook` SET active = '0'";
      $wpdb->query($query_all);

      foreach($_POST['profile'] as $perfil):
        $wpdb->query("UPDATE `".$wpdb->prefix."postofacebook` SET active = '1' WHERE id = '".$perfil."'");
      endforeach;
?>
<div id="message" class="updated fade">
  <p><strong>
    <?php _e('Options saved.', 'att_trans_domain' ); ?>
    </strong></p>
</div>
<?php } ?>

<?php if(!empty($msg)): ?>
  <div id="message" class="updated fade">
  <p><strong>
    <?php _e($msg, 'att_trans_domain' ); ?>
    </strong></p>
</div>
<?php endif; ?>

<div class="wrap">

  <div id="icon-options-general" class="icon32"></div>
  <h2><?php _e( 'Easy Post to Facebook Options', 'att_trans_domain' ); ?></h2>
  <?php
    global $wpdb;
    $count = $wpdb->get_var("SELECT COUNT(*) FROM ".$wpdb->prefix."postofacebook");
    if(!empty($count)):
  ?>
  <form name="att_img_options" method="post" action="<?php echo str_replace( '%7E', '~', $_SERVER['REQUEST_URI']); ?>">
      
      <h3>Accounts to publish</h3>
      <?php // Array de perfis ou páginas selecionados

        $marcados = $wpdb->get_results("SELECT id,ds FROM ".$wpdb->prefix."postofacebook WHERE active = '1'");
        foreach($marcados as $marcado):
          $ar_marcado[$marcado->id] = $marcado->ds;
        endforeach;
      ?>
      <h4>Profiles</h4>
      <?php
        $profiles = $wpdb->get_results("SELECT id,ds FROM ".$wpdb->prefix."postofacebook WHERE type = 'user'");
        foreach($profiles as $profile):
          if(!empty($ar_marcado[$profile->id])):
              $chk = "checked = 'checked'";
          else:
              $chk = "";
          endif;

          echo "<input type='checkbox' name='profile[]' value='".$profile->id."' ".$chk." />".$profile->ds."<br />";
        endforeach;
      ?>

      <h4>Pages</h4>
      <?php
        $profiles = $wpdb->get_results("SELECT id,ds FROM ".$wpdb->prefix."postofacebook WHERE type = 'page'");
        foreach($profiles as $profile):
          if(!empty($ar_marcado[$profile->id])):
              $chk = "checked = 'checked'";
          else:
              $chk = "";
          endif;

          echo "<input type='checkbox' name='profile[]' value='".$profile->id."' ".$chk." />".$profile->ds."<br />";
        endforeach;
      ?>

      <h4>Applications</h4>
      <?php
        $profiles = $wpdb->get_results("SELECT id,ds FROM ".$wpdb->prefix."postofacebook WHERE type = 'app'");
        foreach($profiles as $profile):
          if(!empty($ar_marcado[$profile->id])):
              $chk = "checked = 'checked'";
          else:
              $chk = "";
          endif;

          echo "<input type='checkbox' name='profile[]' value='".$profile->id."' ".$chk." />".$profile->ds."<br />";
        endforeach;
      ?>
<br />
      <input type="hidden" name="<?php echo $hidden_field_name; ?>" value="Y">
      <input class='button-primary' type='submit' name='Save' value='<?php _e('Save Options'); ?>' id='submitbutton' />
  </form>
<br /><br />
<?php endif; ?>
  <h3>Add new account</h3>
   <a href="<?php echo PTF_URL.'&addaccount=true'; ?>"><img src="<?php bloginfo('url'); ?>/wp-content/plugins/easy-post-to-facebook/images/b_facebook.png" /></a><br /><br />


  <h3>Liked the plugin? Please donate!</h3>

<form action="https://www.paypal.com/cgi-bin/webscr" method="post"><input type="hidden" name="cmd" value="_s-xclick" /> <input type="hidden" name="encrypted" value="-----BEGIN PKCS7-----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-----END PKCS7----- " /> <input type="image" name="submit" src="https://www.paypalobjects.com/en_US/i/btn/btn_donateCC_LG.gif" alt="PayPal - The safer, easier way to pay online!" /> <img src="https://www.paypalobjects.com/en_US/i/scr/pixel.gif" alt="" width="1" height="1" border="0" /></form>
<?php if(!function_exists('fb_optimization')): ?>
<h3>Improve your Facebook sharing</h3>
Download the <a href="http://wordpress.org/extend/plugins/facebook-optimize/" target="_blank">Facebook Optimize</a> Wordpress Plugin.
<br /><?php endif; ?>
<br /><br />This plugin uses <a href="http://alter.feelsen.com" target="_blank">Alter</a> technology.</div>

<?php
}

// Actions
add_action('admin_menu', 'ptf_menu');
register_activation_hook( __FILE__, 'ptf_create_table' );
add_action('publish_post', 'send_post',10,2);

?>