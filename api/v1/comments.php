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
		    if (isset($_POST['comment_text']) && !empty(trim($_POST['comment_text'])) &&
		    isset($_POST['comment_post']) && !empty(trim($_POST['comment_post'])) &&
		    isset($_POST['comment_owner']) && !empty(trim($_POST['comment_owner']))) { // share comment
					$comment_text = trim($_POST['comment_text']);
					$comment_post = $_POST['comment_post'];
					$comment_owner = $_POST['comment_owner'];

					if (getUserModel($db, $comment_owner) != 0) { // check user id
						if (getPostModel($db, $comment_post) != 0) { // check post id
							$created_at = date("M d, Y - h:i A"); // Oct 16, 2022 - 16:23 PM

							$share_comment = $db->prepare("INSERT INTO comments (comment_text, comment_post, comment_owner, created_at) VALUES(:comment_text, :comment_post, :comment_owner, :created_at)");
							$share_comment->execute(array(
								":comment_text" => $comment_text,
								":comment_post" => $comment_post,
								":comment_owner" => $comment_owner,
								":created_at" => $created_at
							));

							if ($share_comment->rowCount() > 0) {
								$response['code'] = $code = 200;
								$response['message'] = INFO_SHARED;
							} else {
								$response['code'] = $code = 400;
								$response['message'] = ERROR_MESSAGE;
							}
						} else { // there isn't any post with this id
							$response['code'] = $code = 404;
							$response['message'] = ERROR_NOT_FOUND_POST;
						}
					} else { // there is not a user with this id
						$response['code'] = $code = 404;
						$response['message'] = ERROR_NOT_FOUND_USER;
					}
		    } else { // bad request
		      $response['code'] = $code = 400;
					$response['message'] = httpStatus($code);
		    }
			} else if ($method == "PUT") {
				$data = json_decode(file_get_contents("php://input"));

				if (isset($data->comment_id) && !empty(trim($data->comment_id)) &&
				isset($data->comment_text) && !empty(trim($data->comment_text))) { // update comment
		      $update_comment = $db->prepare("UPDATE comments SET comment_text = :comment_text WHERE comment_id = :comment_id");
		      $update_comment->execute(array(
		        ":comment_text" => trim($data->comment_text),
		        ":comment_id" => $data->comment_id
		      ));

		      if ($update_comment->rowCount() > 0) {
		        $response['code'] = $code = 200;
		        $response['message'] = INFO_UPDATED;
		      } else {
		        $response['code'] = $code = 400;
		  			$response['message'] = ERROR_MESSAGE;
		      }
				} else { // bad request
					$response['code'] = $code = 400;
					$response['message'] = httpStatus($code);
				}
			} else if ($method == "DELETE") {
		    if (isset($_GET['comment_id']) && !empty(trim($_GET['comment_id']))) { // delete comment
					$comment_id = $_GET['comment_id'];

					$delete_comment = $db->prepare("DELETE FROM comments WHERE comment_id = :comment_id");
		      $delete_comment->execute(array(
		        ":comment_id" => $comment_id
		      ));

		      if ($delete_comment->rowCount() > 0) {
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
		}	else { // incorrect username or password
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
