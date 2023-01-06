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
        if (isset($_POST['user_id']) && !empty(trim($_POST['user_id'])) &&
        isset($_POST['post_id']) && !empty(trim($_POST['post_id']))) { // like
					$user_id = $_POST['user_id'];
					$post_id = $_POST['post_id'];

          if (getUserModel($db, $user_id) != 0) { // check user id
						if (getPostModel($db, $post_id) != 0) { // check post id
							$is_liked = $db->prepare("SELECT * FROM post_likes WHERE post_id = :post_id AND user_id = :user_id");
							$is_liked->execute(array(
								":post_id" => $post_id,
								":user_id" => $user_id
							));

							if ($is_liked->rowCount() == 0) { // check if this post isn't liked before by this user
								$like = $db->prepare("INSERT INTO post_likes (post_id, user_id) VALUES(:post_id, :user_id)");
								$like->execute(array(
									":post_id" => $post_id,
									":user_id" => $user_id
								));

								if ($like->rowCount() > 0) {
									$response['code'] = $code = 200;
									$response['message'] = INFO_INSERTED;
								} else {
									$response['code'] = $code = 400;
									$response['message'] = ERROR_MESSAGE;
								}
							} else { // this post is already liked by this user
								$response['code'] = $code = 400;
								$response['message'] = ERROR_ALREADY_LIKED;
							}
						} else { // there isn't any post with this id
							$response['code'] = $code = 404;
							$response['message'] = ERROR_NOT_FOUND_POST;
						}
          } else { // there isn't any user with this id
            $response['code'] = $code = 404;
            $response['message'] = ERROR_NOT_FOUND_USER;
          }
        } else { // bad request
          $response['code'] = $code = 400;
          $response['message'] = httpStatus($code);
        }
			} else if ($method == "DELETE") {
        if (isset($_GET['user_id']) && !empty(trim($_GET['user_id'])) &&
        isset($_GET['post_id']) && !empty(trim($_GET['post_id']))) { // unlike
					$user_id = $_GET['user_id'];
					$post_id = $_GET['post_id'];

					$unlike = $db->prepare("DELETE FROM post_likes WHERE post_id = :post_id AND user_id = :user_id");
					$unlike->execute(array(
						":post_id" => $post_id,
						":user_id" => $user_id
					));

					if ($unlike->rowCount() > 0) {
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
