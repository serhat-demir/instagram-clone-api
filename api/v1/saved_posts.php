<?php
	include 'utils/constants.php';
	include 'utils/db.php';
	include 'utils/functions.php';

	$method = $_SERVER['REQUEST_METHOD'];
	$response = [];
	$code = 200;

	if (isset($_SERVER['PHP_AUTH_USER'])) {
		if ($_SERVER['PHP_AUTH_USER'] == AUTH_USER && $_SERVER['PHP_AUTH_PW'] == AUTH_PW) {
			if ($method == "GET") {
				if (isset($_GET['user_id']) && !empty(trim($_GET['user_id']))) { // get saved posts
					$user_id = $_GET['user_id'];

					if (getUserModel($db, $user_id) != 0) { // check user id
						$saved_posts = $db->prepare("SELECT * FROM saved_posts WHERE user_id = :user_id ORDER BY post_id DESC");
						$saved_posts->execute(array(
							":user_id" => $user_id
						));

						if ($saved_posts->rowCount() > 0) {
							$saved_posts = $saved_posts->fetchAll(PDO::FETCH_ASSOC);

							$response['code'] = $code = 200;
							$response['message'] = httpStatus($code);

							$response['posts'] = [];

							for ($i = 0; $i < count($saved_posts); $i++) {
								array_push($response['posts'], getPostModel($db, $saved_posts[$i]['post_id']));
							}
						} else { // there isn't any saved post
							$response['code'] = $code = 404;
							$response['message'] = ERROR_NOT_FOUND;
						}
					} else { // there isn't any user with this id
						$response['code'] = $code = 404;
						$response['message'] = ERROR_NOT_FOUND_USER;
					}
				} else { // bad request
					$response['code'] = $code = 400;
					$response['message'] = httpStatus($code);
				}
			} else if ($method == "POST") {
				if (isset($_POST['user_id']) && !empty(trim($_POST['user_id'])) &&
				isset($_POST['post_id']) && !empty(trim($_POST['post_id']))) { // save post
					$user_id = $_POST['user_id'];
					$post_id = $_POST['post_id'];

					// check user id
          if (getUserModel($db, $user_id) != 0) {
						if (getPostModel($db, $post_id) != 0) { // check post id
							$is_saved = $db->prepare("SELECT * FROM saved_posts WHERE post_id = :post_id AND user_id = :user_id");
							$is_saved->execute(array(
								":post_id" => $post_id,
								":user_id" => $user_id
							));

							if ($is_saved->rowCount() == 0) { // check if this post isn't saved before
								$save = $db->prepare("INSERT INTO saved_posts (post_id, user_id) VALUES(:post_id, :user_id)");
								$save->execute(array(
									":post_id" => $post_id,
									":user_id" => $user_id
								));

								if ($save->rowCount() > 0) {
									$response['code'] = $code = 200;
									$response['message'] = INFO_INSERTED;
								} else {
									$response['code'] = $code = 400;
									$response['message'] = ERROR_MESSAGE;
								}
							} else { // this post is already saved
								$response['code'] = $code = 400;
								$response['message'] = ERROR_ALREADY_SAVED;
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
				isset($_GET['post_id']) && !empty(trim($_GET['post_id']))) { // unsave post
					$user_id = $_GET['user_id'];
					$post_id = $_GET['post_id'];

					$unsave = $db->prepare("DELETE FROM saved_posts WHERE post_id = :post_id AND user_id = :user_id");
					$unsave->execute(array(
						":post_id" => $post_id,
						":user_id" => $user_id
					));

					if ($unsave->rowCount() > 0) {
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
