<?php
    function getUserModel($db, $user_id, $is_password_hidden = 0, $include_follow = 1) {
  		$user_details = $db->prepare("SELECT * FROM users WHERE user_id = :user_id");
  		$user_details->execute(array(
  			":user_id" => $user_id
  		));

  		if ($user_details->rowCount() > 0) {
  			$user_details = $user_details->fetchAll(PDO::FETCH_ASSOC)[0];

  			if ($is_password_hidden == 1) {
  				$user_details['user_email'] = "";
  				$user_details['user_password'] = "";
  			}

        // NULL -> empty string
        if ($user_details['user_fullname'] == NULL) $user_details['user_fullname'] = "";
        if ($user_details['user_bio'] == NULL) $user_details['user_bio'] = "";

        if ($include_follow == 1) {
          $get_followers = $db->prepare("SELECT * FROM follow WHERE following_id = :user_id");
          $get_following = $db->prepare("SELECT * FROM follow WHERE follower_id = :user_id");

          $get_followers->execute(array(
            ":user_id" => $user_id
          ));

          $get_following->execute(array(
            ":user_id" => $user_id
          ));

          $user_details['followers'] = $get_followers->fetchAll(PDO::FETCH_ASSOC);
          $user_details['following'] = $get_following->fetchAll(PDO::FETCH_ASSOC);

          for ($i = 0; $i < count($user_details['followers']); $i++) {
            $user_details['followers'][$i] = getUserModel($db, $user_details['followers'][$i]['follower_id'], 1, 0);
          }

          for ($i = 0; $i < count($user_details['following']); $i++) {
            $user_details['following'][$i] = getUserModel($db, $user_details['following'][$i]['following_id'], 1, 0);
          }
        } else {
          $user_details['followers'] = [];
          $user_details['following'] = [];
        }

  			return $user_details;
  		} else { // there isn't any user with this id
  			return 0;
  		}
  	}

    function getPostModel($db, $post_id, $include_all_data = 1) {
  		$post_details = $db->prepare("SELECT * FROM posts WHERE post_id = :post_id");
  		$post_details->execute(array(
  			":post_id" => $post_id
  		));

  		if ($post_details->rowCount() > 0) {
  			$post_details = $post_details->fetchAll(PDO::FETCH_ASSOC)[0];

        if ($include_all_data == 1) {
          // post owner
          $post_details['post_owner'] = getUserModel($db, $post_details['post_owner'], 1, 0);

          // likers
          $likers = $db->prepare("SELECT * FROM post_likes WHERE post_id = :post_id");
          $likers->execute(array(
            ":post_id" => $post_details['post_id']
          ));

          $post_details['likers'] = $likers->fetchAll(PDO::FETCH_ASSOC);
          for ($i = 0; $i < count($post_details['likers']); $i++) { // hide emails and passwords of users & set followers and following
            $post_details['likers'][$i] = getUserModel($db, $post_details['likers'][$i]['user_id'], 1, 0);
          }

          // comments
          $comments = $db->prepare("SELECT * from comments WHERE comment_post = :post_id");
          $comments->execute(array(
            ":post_id" => $post_details['post_id']
          ));

          $post_details['comments'] = $comments->fetchAll(PDO::FETCH_ASSOC);
          for ($i = 0; $i < count($post_details['comments']); $i++) { // get comment owners for each comment
            $post_details['comments'][$i]['comment_owner'] = getUserModel($db, $post_details['comments'][$i]['comment_owner'], 1, 0);
          }
        } else {
          $post_details['post_owner'] = new stdClass;
          $post_details['likers'] = [];
          $post_details['comments'] = [];
        }

  			return $post_details;
  		} else { // there isn't any post with this id
  			return 0;
  		}
  	}

    function httpStatus($code) {
     $status = array(
            100 => 'Continue',
            101 => 'Switching Protocols',
            200 => 'OK',
            201 => 'Created',
            202 => 'Accepted',
            203 => 'Non-Authoritative Information',
            204 => 'No Content',
            205 => 'Reset Content',
            206 => 'Partial Content',
            300 => 'Multiple Choices',
            301 => 'Moved Permanently',
            302 => 'Found',
            303 => 'See Other',
            304 => 'Not Modified',
            305 => 'Use Proxy',
            306 => '(Unused)',
            307 => 'Temporary Redirect',
            400 => 'Bad Request',
            401 => 'Unauthorized',
            402 => 'Payment Required',
            403 => 'Forbidden',
            404 => 'Not Found',
            405 => 'Method Not Allowed',
            406 => 'Not Acceptable',
            407 => 'Proxy Authentication Required',
            408 => 'Request Timeout',
            409 => 'Conflict',
            410 => 'Gone',
            411 => 'Length Required',
            412 => 'Precondition Failed',
            413 => 'Request Entity Too Large',
            414 => 'Request-URI Too Long',
            415 => 'Unsupported Media Type',
            416 => 'Requested Range Not Satisfiable',
            417 => 'Expectation Failed',
            500 => 'Internal Server Error',
            501 => 'Not Implemented',
            502 => 'Bad Gateway',
            503 => 'Service Unavailable',
            504 => 'Gateway Timeout',
            505 => 'HTTP Version Not Supported'
        );

        return $status[$code] ? $status[$code] : $status[500];
    }

    function setHeader($code){
        header("HTTP/1.1 " . $code . " " . httpStatus($code));
        header("Content-Type: application/json; charset=utf-8");
    }
?>
