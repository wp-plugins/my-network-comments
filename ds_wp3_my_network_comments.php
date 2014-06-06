<?php
  /*
   Plugin Name: My Network Comments
   Plugin URI: http://wordpress.org/extend/plugins/my-network-comments/
   Description: Tracks logged in network user comments on any site in the network: Dashboard->My Network Comments. Network Activate.
   Version: 3.9.1
   Author: D. Sader
   Author URI: http://dsader.snowotherway.org
   Network: true
   
   This program is free software; you can redistribute it and/or modify
   it under the terms of the GNU General Public License as published by
   the Free Software Foundation; either version 2 of the License, or
   (at your option) any later version.
   
   This program is distributed in the hope that it will be useful,
   but WITHOUT ANY WARRANTY; without even the implied warranty of
   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
   GNU General Public License for more details.
   
   Performance notes: tested on a small WPMU install(>200 blogs). I do not know how this plugin performs in a large install. An array of "Blog_id, post_id" pairs are stored in usermeta->LatestComments to a defined maximum, called then sliced by page number, looped to retrieve posts, then each post looped to retrieve comments - "My Network Comments" and "Other comments". The bottom of the My Network Comments page has a numqueries count, FYI(if SuperAdmin). Redefining the MY_POSTS_APAGE constant can throttle the page somewhat.
   
   Note:
   Usermeta is not updated sitewide when comments/blogs are deleted. Instead The status of the comment is reported and User can "Dismiss" further tracking of the comment/post.
   
   Changes
   update_user_meta replaces update_usermeta
   */
  /***********I thought about making these constants Network options at SuperAdmin->Options, but what if an inexperienced SuperAdmin cranks the queries beyong the limits of CPU/Memory? ***************/
  if (!defined('MY_COMMENTS_TRACKED'))
      // pagination, each row = post, more = more loops for posts/comments per page
      define('MY_COMMENTS_TRACKED', '100');
  if (!defined('MY_POSTS_APAGE'))
      // pagination, each row = post, more = more loops for posts/comments per page
      define('MY_POSTS_APAGE', '20');
  if (!defined('MY_COMMENTS_APOST'))
      // how many mycomments per post(at least 1), first loop for comments pull all comments matchng logged in user, most recent at top.
      define('MY_COMMENTS_APOST', '1');
  if (!defined('OTHERS_COMMENTS_APOST'))
      // how many other comments(at least 1), second loop for all comments not matching logged in user, most recent at top
      define('OTHERS_COMMENTS_APOST', '1');
  if (!defined('POST_EXCERPT_LENGTH'))
      define('POST_EXCERPT_LENGTH', '400');
  if (!defined('COMMENT_EXCERPT_LENGTH'))
      define('COMMENT_EXCERPT_LENGTH', '250');

class My_Network_Comments {
     		var $l10n_prefix;
 
  function My_Network_Comments () { 
  	$this->l10n_prefix = 'my-network-comments';   
	add_action( 'init', array(&$this, 'ds_localization_init' ));
  	add_action( 'admin_menu', array(&$this, 'ds_tracked_comments_addmenu' ));
  	add_action( 'comment_post', array(&$this, 'ds_track_user_comment_posting' ));
  	add_action( 'trackback_post', array(&$this, 'ds_track_user_comment_posting' ));
  	add_action( 'admin_init',array(&$this, 'ds_my_comments_update' ));
  }
  function ds_localization_init() {
		load_plugin_textdomain( 'my-network-comments', false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
//		load_plugin_textdomain( $this->l10n_prefix, false, dirname( plugin_basename( __FILE__ ) ) . '/languages/');
	}

  function ds_tracked_comments_addmenu() {
      $page = add_submenu_page('index.php', __('My Network Comments', $this->l10n_prefix), __('My Network Comments', $this->l10n_prefix), 'moderate_comments', 'my_network_comments', array(&$this, 'ds_my_network_comments' ));
  }
  function ds_track_user_comment_posting() {
      global $current_blog, $comment, $post, $current_user, $comment_post_ID;
      $thisnewcomment = array($current_blog->blog_id, $comment_post_ID, );
      $addcomment = implode(", ", $thisnewcomment);
      $LatestCommentsList = $current_user->LatestComments;
      if (!$LatestCommentsList) {
          $LatestCommentsList = array();
//      } else {
  //        $array = explode(", ", $current_user->LatestComments);
      }
      if (!in_array($addcomment, $LatestCommentsList)) {
          array_push($LatestCommentsList, $addcomment);
          if (count($LatestCommentsList) > MY_COMMENTS_TRACKED) {
              // only last 100 posts tracked sliced by pagination below
              // no loop, so if new limit then count = old limit - 1
              unset($LatestCommentsList[0]);
              array_unshift($LatestCommentsList, array_shift($LatestCommentsList));
              if (!isset($LatestCommentsList[0])) {
                  $LatestCommentsList = array();
              }
          }
      }
      update_user_meta($current_user->ID, 'LatestComments', $LatestCommentsList);
  }
  // Comments-->My Network Comments
  function ds_my_comments_update() {
      global $current_user;
      $LatestCommentsList = $current_user->LatestComments;
              if (isset($_GET['action'])) {
              	 $LatestCommentsList = $current_user->LatestComments;
                  if ('stoptrack' == $_GET['action']) {
                      $trackedblog = $_GET['trackedblog'];
                      $trackedpost = $_GET['trackedpost'];
                      $dismiss_comment = array($trackedblog, $trackedpost);
                      $dismiss_latest = implode(", ", $dismiss_comment);
                      if (in_array($dismiss_latest, $LatestCommentsList)) {
                          unset($LatestCommentsList[array_search($dismiss_latest, $LatestCommentsList)]);
                          array_unshift($LatestCommentsList, array_shift($LatestCommentsList));
                          if (!isset($LatestCommentsList[0])) {
                              $LatestCommentsList = array();
                          }
                      }
                      update_user_meta($current_user->ID, 'LatestComments', $LatestCommentsList);
                      $location = wp_get_referer();
                      $location = add_query_arg(array('action' => 'dismissed', ));
                      wp_redirect($location); // add_action must be before admin_head otherwise headers already sent errors
                   //   echo "<meta http-equiv='refresh' content='0;url=$location' />";
                  }
              }	
  }
  function ds_my_network_comments() {
      global $current_user, $wpdb;
      $LatestCommentsList = $current_user->LatestComments;
              if (isset($_GET['action'])) {
                  if ('dismissed' == $_GET['action']) {
                      echo "<div class='updated'><p>Comment Dismissed.</p></div>";
                  }
              }
?>  <div class="wrap">
  <h2><?php _e('My Network Comments', $this->l10n_prefix); ?></h2>
     <?php
      if (!$LatestCommentsList) {
?>
    <p><?php _e('Write a comment on any site while logged in, then revisit this page to view your comments being tracked', $this->l10n_prefix); ?>.</p>
    <?php
          } else {
              $LatestCommentsList = array_reverse($LatestCommentsList);
              $pre_paginate = count($LatestCommentsList);
              $posts_per_page = MY_POSTS_APAGE;
              if (isset($_GET['apage']))
                  $page = absint($_GET['apage']);
              if (empty($page))
                  $page = 1;
              $start = $offset = ($page - 1) * $posts_per_page;
              $page_links = paginate_links(array('base' => add_query_arg('apage', '%#%'), 'format' => '', 'total' => ceil($pre_paginate / $posts_per_page), 'current' => $page));
              $LatestCommentsList = array_slice($LatestCommentsList, $start, $posts_per_page);
              $post_paginate = count($LatestCommentsList);
?>
    <p><?php _e( 'Any posts you comment on are tracked here. Only the most recent comments are shown. The post with the most recent comments is shown first with your most recent comment, followed by the most recent reply. When you want to stop tracking a particular comment, dismiss it', $this->l10n_prefix); ?>.</p>

  <form id="comments-form" action="" method="get">
  <div class="tablenav">
  <?php
              echo $current_user->display_name . ' '.__('is tracking', $this->l10n_prefix).' ' . $pre_paginate . '/' . MY_COMMENTS_TRACKED . ' '.__('post comments', $this->l10n_prefix).' ... ' . $post_paginate . ' '.__('on this page', $this->l10n_prefix).'.';
              if ($page_links)
                  : echo "<div class='tablenav-pages'>$page_links</div>";
              endif;
?>
  </div>
  <br class="clear" />
  <table class="widefat">
    <thead>
      <tr>
        <th scope="col" width="45%"><?php _e('Post', $this->l10n_prefix) ?></th>
        <th scope="col" width="40%"><?php _e('Comments', $this->l10n_prefix) ?></th>
        <th scope="col" class="action-links" width="15%"><?php  _e('Actions', $this->l10n_prefix) ?></th>
      </tr>
    </thead>
  <tbody id="the-comment-list" class="list:comment">
    <?php                   
              foreach ($LatestCommentsList as $key => $tmp_comment_post_array) {
                  $thiscomment = explode(", ", $tmp_comment_post_array);
	                  
	                  $blog_id = $thiscomment[0];
                      $post_id = $thiscomment[1];

                  $details = get_blog_details($blog_id);
       ?>
<tr>
<?php           
                  if ($details) {
                      // does blog still exist?
                      //cache built by get_blog_post in ms-funcitons.php
                      $thispost = get_blog_post($blog_id, $post_id);
                                   
                      $blogCommentsTable = $wpdb->get_blog_prefix($blog_id) . "comments";
                      $MY_COMMENTS_APOST = MY_COMMENTS_APOST;
                $mycomments = $wpdb->get_results("SELECT *
    FROM $blogCommentsTable
    WHERE user_id = $current_user->ID /* flip filter here user_id !=  $current_user->ID */
    AND comment_post_ID = '$post_id'
    ORDER BY comment_date
    DESC
    LIMIT 0,$MY_COMMENTS_APOST
    ", ARRAY_A);

                      $post_permalink = get_blog_permalink($blog_id, $post_id);
                      $OTHERS_COMMENTS_APOST = OTHERS_COMMENTS_APOST;

                      $others_comments = $wpdb->get_results("SELECT *
    FROM $blogCommentsTable
    WHERE user_id !=  $current_user->ID /* the flip */
    AND comment_post_ID = '$post_id'
    AND comment_approved = '1'
    ORDER BY comment_date
    DESC
    LIMIT 0,$OTHERS_COMMENTS_APOST
    ", ARRAY_A);

                      if ($thispost){
                      	$post_type = $thispost->post_type;
                      	$post_status = $thispost->post_status;
                        if( $thispost->post_status == 'publish' ) {
	                    	$post_status = __('published', $this->l10n_prefix);
                        	$class = __('published', $this->l10n_prefix);
                        }
                        if( $thispost->post_status == 'draft' ) {
	                    	$post_status = __('draft', $this->l10n_prefix);
                        	$class = __('unapproved', $this->l10n_prefix);
                        }
                        if( $thispost->post_status == 'pending' ) {
	                    	$post_status = __('pending review', $this->l10n_prefix);
                        	$class = __('unapproved', $this->l10n_prefix);
                        }
                        if( $thispost->post_status == 'trash' ) {
	                    	$post_status = __('in the trash', $this->l10n_prefix);
                        	$class = __('unapproved', $this->l10n_prefix);
                        }
                        if( $thispost->post_status == NULL ) {
	                    	$post_status = __('deleted', $this->l10n_prefix);
                        	$class = __('unapproved', $this->l10n_prefix);
                        }
                        $post_author_details = get_userdata($thispost->post_author);
?>
    <td class="<?php echo $class; ?>">
      <div style="float:left;margin-top:12px;margin-right:12px;margin-bottom:6px;"><?php echo get_avatar($thispost->post_author, 64); ?><br /><cite><?php echo $post_author_details->display_name; ?></cite>
      </div>
      <h3><a href="<?php echo $post_permalink; ?>"><?php echo $thispost->post_title; ?></a></h3>
      <p><?php echo wp_html_excerpt($thispost->post_content, POST_EXCERPT_LENGTH); ?></p>
      <p><?php _e('From', $this->l10n_prefix); ?> <a href="<?php echo $details->siteurl; ?>"><?php echo $details->blogname; ?></a>, <?php  echo mysql2date(get_option('date_format'), $thispost->post_date); ?></p>
      <p><?php echo ucfirst($post_type) .' '. __( 'status', $this->l10n_prefix).': ' . $post_status; ?>.</p>
     </td>
    <td>
    <?php
    if($mycomments) {
                          foreach ($mycomments as $mycomment) {
                              $myoutput = $mycomment['comment_content'];
                              $myoutput = wp_html_excerpt($myoutput, COMMENT_EXCERPT_LENGTH);
                              $comment_type = $mycomment['comment_type'];
                              $comment_status = $mycomment['comment_approved'];
                              if ($comment_status == 1) {
                                  $status = __('approved', $this->l10n_prefix);
                                  $class = __('approved', $this->l10n_prefix);
                              }
                              if ($comment_status == 0) {
                                  $status = __('awaiting moderation', $this->l10n_prefix);
                                  $class = __('unapproved', $this->l10n_prefix);
                              }
                              if ($comment_status == 'trash') {
                                  $status = __('in the trash', $this->l10n_prefix);
                                  $class = __('unapproved', $this->l10n_prefix);
                              }
                              if ($comment_status == 'spam') {
                                  $status = __('spam', $this->l10n_prefix);
                                  $class = __('unapproved', $this->l10n_prefix);
                              }
                              if ($comment_status == null) {
                                  $status = __('deleted', $this->l10n_prefix);
                                  $class = __('unapproved', $this->l10n_prefix);
                              }
                              if ($comment_type == 'trackback')
                                  $type = __('Trackback', $this->l10n_prefix);
                              if (!$comment_type == 'trackback')
                                  $type = __('Comment', $this->l10n_prefix);
?>
        <div class="<?php echo $class; ?>">
          <div style="float:right;margin-left:5px;margin-top:5px;"><?php echo get_avatar($mycomment['user_id'], 32); ?>
          </div>
          <p><?php echo $mycomment['comment_author']; ?> <?php _e('last replied', $this->l10n_prefix); ?>:<br /><?php echo mysql2date('l jS \of F Y h:i:s A', $mycomment['comment_date']); ?></p>
          <p><?php echo $myoutput; ?></p>
          <p><?php echo $type .' '. __( 'status', $this->l10n_prefix).': ' . $status; ?>.</p>
        </div>
        <?php
                          }
			    } else { // if no $mycomments
    	       echo '<div class="unapproved"><p>'.__('Comment by', $this->l10n_prefix).' ' . $current_user->display_name . ' '.__('deleted', $this->l10n_prefix).'.</p></div>'; 

    }
                          if (!$others_comments) {
                               echo '<div class="alternate"><p>'.__('No other replies', $this->l10n_prefix).'.</p></div>';
                          } else {
                              foreach ($others_comments as $others_comment) {
                                  $others_output = $others_comment['comment_content'];
                                  $others_output = wp_html_excerpt($others_output, COMMENT_EXCERPT_LENGTH);
?>
        <div class="alternate">
          <div style="float:right;margin-left:5px;margin-top:5px;"><?php echo get_avatar($others_comment['comment_author_email'], 32);
?>
          </div>
         	<p>
          		<?php echo $others_comment['comment_author'].' '.__('last replied', $this->l10n_prefix); ?>:
				<br />
				<?php echo mysql2date('l jS \of F Y h:i:s A', $others_comment['comment_date']); ?>
			</p>
          <p><?php echo $others_output; ?></p>
        </div>
        <?php
                              }
                          }



if( 'open' == $thispost->comment_status ) $open_closed = __('open', $this->l10n_prefix);
if( 'closed' == $thispost->comment_status ) $open_closed = __('closed', $this->l10n_prefix);

?>
        <p><a href="<?php echo $post_permalink . '#comments'; ?>"><?php echo $thispost->comment_count .' '.__('total comments', $this->l10n_prefix).'. '. __('Comments are', $this->l10n_prefix).' '.$open_closed ; ?>.</a></p>
    </td>
    <?php
                      } else { // if no $thepost
?>
    <td class="unapproved"><strike><?php _e('post deleted', $this->l10n_prefix); ?></strike>
      <p><small><a href="<?php echo $details->siteurl; ?>"><?php echo $details->blogname; ?></a></small></p>
    </td>
    <td class="unapproved">
      <p><?php _e('Comment deleted because post deleted', $this->l10n_prefix); ?>.</p>
    </td>
    <?php
                          }
                      } else { // if no $blog_id
?>
    <td class="unapproved"><strike><?php _e('no site', $this->l10n_prefix); ?></strike></td>
    <td class="unapproved">
      <p><?php _e('Comment deleted because site deleted', $this->l10n_prefix); ?>.</p>
    </td>
<?php
                      }
                      $action_link = $_SERVER['REQUEST_URI'];
                      if (isset($thispost->ID)) {
                          $action_link = add_query_arg(array('action' => 'stoptrack', 'trackedblog' => $blog_id, 'trackedpost' => $thispost->ID));
                          $action_text = __('Dismiss', $this->l10n_prefix);
                          $reply_text = __('Reply', $this->l10n_prefix).' | ';
                          $reply_link = $post_permalink . '#comment';
                      } elseif (!isset($thispost->ID) && isset($details->siteurl)) {
                          $action_link = add_query_arg(array('action' => 'stoptrack', 'trackedblog' => $blog_id, 'trackedpost' => $post_id));
                          $action_text = __('Dismiss', $this->l10n_prefix);
                          $reply_text = __('Reply to another post', $this->l10n_prefix).' | ';
                          $reply_link = $details->siteurl;
                      } else {
                          $action_link = add_query_arg(array('action' => 'stoptrack', 'trackedblog' => $blog_id, 'trackedpost' => $post_id));
                          $action_text = __('Delete', $this->l10n_prefix);
                      }
?>
      <td>
        <?php
        if ($details) {
                      if (($details->spam == 1) || ($details->deleted == 1) || ($details->archived == 1)) {
                          echo '|';
                      } else {
?>
        <p class="" ><a href="<?php echo $reply_link; ?>"><?php echo $reply_text; ?></a>
        <?php
                      }
        }
?>
        <a href="<?php echo $action_link; ?>"><?php echo $action_text; ?></a></p>
      </td>
    </tr>
    <?php
                      } // foreach Latestcomment
?>
    </tbody>
  </table>
  </form>
  <?php
                  }
                  if (is_super_admin())
                      echo '<p>SuperAdmin, get_num_queries() = ' . get_num_queries() . '. '.__('Constants controlling the post/pagination/comment arrays can be defined near the top of the plugin code', $this->l10n_prefix).'.</p>';
                  // wrap
                  echo '</div>';
  }
  
}
if (class_exists("My_Network_Comments")) {
	$My_Network_Comments = new My_Network_Comments();	
}
?>