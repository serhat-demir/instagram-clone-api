<?php
	include 'utils/constants.php';
	include 'utils/db.php';
	include 'utils/functions.php';

	$method = $_SERVER['REQUEST_METHOD'];
	$response = [];
	$code = 200;

	if (isset($_SERVER['PHP_AUTH_USER'])) {
		if ($_SERVER['PHP_AUTH_USER'] == AUTH_USER && $_SERVER['PHP_AUTH_PW'] == AUTH_PW) {
			if ($method == "POST") {
        if (isset($_POST['follower_id']) && !empty(trim($_POST['follower_id'])) &&
        isset($_POST['following_id']) && !empty(trim($_POST['following_id']))) { // follow
					$follower_id = $_POST['follower_id'];
					$following_id = $_POST['following_id'];

          if (getUserModel($db, $follower_id) != 0) { // check follower
            if (getUserModel($db, $following_id) != 0) { // check following
              $is_following = $db->prepare("SELECT * FROM follow WHERE follower_id = :follower_id AND following_id = :following_id");
              $is_following->execute(array(
                ":follower_id" => $follower_id,
                ":following_id" => $following_id
              ));

              if ($is_following->rowCount() == 0) { // check if this user is following the other user or not
                if ($follower_id != $following_id) { // check if the user is trying to follow himself
                  $follow = $db->prepare("INSERT INTO follow (follower_id, following_id) VALUES(:follower_id, :following_id)");
                  $follow->execute(array(
                    ":follower_id" => $follower_id,
                    ":following_id" => $following_id
                  ));

                  if ($follow->rowCount() > 0) {
                    $response['code'] = $code = 200;
                    $response['message'] = INFO_INSERTED;
                  } else {
                    $response['code'] = $code = 400;
                    $response['message'] = ERROR_MESSAGE;
                  }
                } else { // user is trying to follow himself
                  $response['code'] = $code = 400;
                  $response['message'] = ERROR_YOU_CANT_FOLLOW_YOURSELF;
                }
              } else { // this user is already following the other user
                $response['code'] = $code = 400;
                $response['message'] = ERROR_ALREADY_FOLLOWING;
              }
            } else { // there isn't any user with the following's id
              $response['code'] = $code = 400;
              $response['message'] = ERROR_NOT_FOUND_USER . " (following)";
            }
          } else { // there isn't any user with the follower's id
            $response['code'] = $code = 400;
            $response['message'] = ERROR_NOT_FOUND_USER . " (follower)";
          }
        } else { // bad request
          $response['code'] = $code = 400;
          $response['message'] = httpStatus($code);
        }
      } else if ($method == "DELETE") {
        if (isset($_GET['follower_id']) && !empty(trim($_GET['follower_id'])) &&
        isset($_GET['following_id']) && !empty(trim($_GET['following_id']))) { // unfollow
					$follower_id = $_GET['follower_id'];
					$following_id = $_GET['following_id'];

					$unfollow = $db->prepare("DELETE FROM follow WHERE follower_id = :follower_id AND following_id = :following_id");
          $unfollow->execute(array(
            ":follower_id" => $follower_id,
            ":following_id" => $following_id
          ));

          if ($unfollow->rowCount() > 0) {
						$response['code'] = $code = 200;
						$response['message'] = INFO_DELETED;
					} else {
						$response['code'] = $code = 400;
						$response['message'] = ERROR_MESSAGE;
					}
        } else { // bad request
          $response['code'] = $code = 400;
          $response['message'] = httpStatus($code);
        }
			} else { // different request method
			  $response['code'] = $code = 405;
				$response['message'] = httpStatus($code);
			}
		} else { // incorrect username or password
			$response['code'] = $code = 401;
			$response['message'] = httpStatus($code);
		}
	} else { // unauthorized
		$response['code'] = $code = 401;
		$response['message'] = httpStatus($code);
	}

	setHeader($code);
	echo json_encode($response);
?>
