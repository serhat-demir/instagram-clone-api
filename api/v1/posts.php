<?php
	include 'utils/constants.php';
	include 'utils/db.php';
	include 'utils/functions.php';

	$method = $_SERVER['REQUEST_METHOD'];
	$response = [];
	$code = 200;

	function getPostList($user_id, $is_feed) {
		global $db;
		$response_temp = [];

		if ($is_feed == 1) { // feed
			$following = $db->prepare("SELECT follow.following_id FROM follow INNER JOIN users ON follow.following_id = users.user_id WHERE follow.follower_id = :user_id AND users.user_profile_private = 0");
			$following->execute(array(
				":user_id" => $user_id
			));

			$following = $following->fetchAll(PDO::FETCH_COLUMN);
			array_push($following, $user_id);

			$following = implode(",", $following);

			$query = "SELECT * FROM posts WHERE post_owner IN(" . $following . ") ORDER BY post_id DESC";
		} else { // posts (in user profile)
			$query = "SELECT * FROM posts WHERE post_owner = " . $user_id . " ORDER BY post_id DESC";
		}

		$posts = $db->prepare($query);
		$posts->execute();

		if ($posts->rowCount() > 0) {
			$posts = $posts->fetchAll(PDO::FETCH_ASSOC);

			for ($i = 0; $i < count($posts); $i++) {
				$posts[$i] = getPostModel($db, $posts[$i]['post_id']);
			}

			$response_temp['code'] = $code = 200;
			$response_temp['message'] = httpStatus($code);

			$response_temp['posts'] = $posts;
		} else { // there isn't any post
			$response_temp['code'] = $code = 404;
			$response_temp['message'] = ERROR_NOT_FOUND;
		}

		return $response_temp;
	}

	if (isset($_SERVER['PHP_AUTH_USER'])) {
		if ($_SERVER['PHP_AUTH_USER'] == AUTH_USER && $_SERVER['PHP_AUTH_PW'] == AUTH_PW) {
			if ($method == "GET") {
				if (isset($_GET['user_id']) && !empty(trim($_GET['user_id'])) && isset($_GET['is_feed']) && ($_GET['is_feed'] == 0 || $_GET['is_feed'] == 1)) {
					$user_id = $_GET['user_id'];
					$is_feed = $_GET['is_feed'];

					if (getUserModel($db, $user_id) != 0) { // check user id
						$response = getPostList($user_id, $is_feed);
					} else { // there isn't any user with this id
						$response['code'] = $code = 404;
						$response['message'] = ERROR_NOT_FOUND_USER;
					}
				} else if (isset($_GET['post_id']) && !empty(trim($_GET['post_id']))) { // post details
					$post_id = $_GET['post_id'];

					if (getPostModel($db, $post_id) != 0) {
						$response['code'] = $code = 200;
						$response['message'] = httpStatus($code);

						$response['post'] = getPostModel($db, $post_id);
					} else { // there isn't any post with this id
						$response['code'] = $code = 404;
						$response['message'] = ERROR_NOT_FOUND_POST;
					}
				} else { // bad request
					$response['code'] = $code = 400;
					$response['message'] = httpStatus($code);
				}
			} else if ($method == "POST") {
				if (isset($_FILES['image']) && isset($_POST['post_description']) && !empty(trim($_POST['post_description'])) &&
				isset($_POST['post_owner']) && !empty(trim($_POST['post_owner']))) {
					$post_description = trim($_POST['post_description']);
					$post_owner = trim($_POST['post_owner']);

					if (getUserModel($db, $post_owner) != 0) { // check user id & share post
						$image = $_FILES['image'];
						$error = $image['error'];

						if($error != 0) {
							$response['code'] = $code = 400;
							$response['message'] = ERROR_MESSAGE;
						} else {
							$image_size = $image['size'];

							if ($image_size > (1024 * 1024 * 10)) { // size = byte, (1024^2 * 10) = 10 megabyte
								$response['code'] = $code = 400;
								$response['message'] = ERROR_IMG_SIZE;
							} else {
								$image_type = $image['type'];
								$image_name = $image['name'];
								$image_extension = explode('.', $image_name); // [name, extension]
								$image_extension = $image_extension[count($image_extension) - 1]; // last index

								$new_name = time() . '.' . $image_extension;
								$new_path = DIR_POST_PHOTOS . $new_name; // post_photos/name.extension

								if ($image_extension == 'jpg' || $image_extension == 'jpeg' || $image_extension == 'png') {
									if (move_uploaded_file($image['tmp_name'], $new_path)) {
										$created_at = date("M d, Y - h:i A"); // Oct 16, 2022 - 16:23 PM

										$share_post = $db->prepare("INSERT INTO posts (post_photo, post_description, post_owner, created_at) VALUES(:post_photo, :post_description, :post_owner, :created_at)");
										$share_post->execute(array(
											":post_photo" => $new_name,
											":post_description" => $post_description,
											":post_owner" => $post_owner,
											":created_at" => $created_at
										));

										if ($share_post->rowCount() > 0) {
											$response['code'] = $code = 200;
											$response['message'] = INFO_SHARED;
										} else {
											$response['code'] = $code = 400;
											$response['message'] = ERROR_MESSAGE;
										}
									} else { // file error
										$response['code'] = $code = 400;
										$response['message'] = ERROR_MESSAGE;
									}
								} else { // image type error
									$response['code'] = $code = 400;
									$response['message'] = ERROR_IMG_TYPE;
								}
							}
						}
					} else { // there isn't any user with this id
						$response['code'] = $code = 404;
						$response['message'] = ERROR_NOT_FOUND_USER;
					}
				} else { // bad request
					$response['code'] = $code = 400;
					$response['message'] = httpStatus($code);
				}
			} else if ($method == "PUT") {
				$data = json_decode(file_get_contents("php://input"));

				if (isset($data->post_id) && !empty(trim($data->post_id)) &&
				isset($data->post_description) && !empty(trim($data->post_description))) { // update post
					if (getPostModel($db, $data->post_id) != 0) { // check post id
						$update_post = $db->prepare("UPDATE posts SET post_description = :post_description WHERE post_id = :post_id");
						$update_post->execute(array(
							":post_description" => trim($data->post_description),
							":post_id" => $data->post_id
						));

						if ($update_post->rowCount() > 0) {
							$response['code'] = $code = 200;
							$response['message'] = INFO_UPDATED;
						} else {
							$response['code'] = $code = 400;
							$response['message'] = ERROR_MESSAGE;
						}
					} else { // there isn't any post with this id
						$response['code'] = $code = 404;
						$response['message'] = ERROR_NOT_FOUND_POST;
					}
				} else { // bad request
					$response['code'] = $code = 400;
					$response['message'] = httpStatus($code);
				}
			} else if ($method == "DELETE") {
				if (isset($_GET['post_id']) && !empty(trim($_GET['post_id']))) { // delete post
					$post_id = $_GET['post_id'];

					$post_photo = $db->prepare("SELECT post_photo FROM posts WHERE post_id = :post_id");
					$post_photo->execute(array(
						":post_id" => $post_id
					));

					if ($post_photo->rowCount() > 0) {
						$post_photo = $post_photo->fetchAll(PDO::FETCH_ASSOC)[0]['post_photo'];

						if (unlink(DIR_POST_PHOTOS . $post_photo)) { // delete post photo
							$delete_post = $db->prepare("DELETE FROM posts WHERE post_id = :post_id");
							$delete_post->execute(array(
								":post_id" => $post_id
							));

							if ($delete_post->rowCount() > 0) {
								$response['code'] = $code = 200;
								$response['message'] = INFO_DELETED;
							} else {
								$response['code'] = $code = 400;
								$response['message'] = ERROR_MESSAGE;
							}
						} else { // post photo couldn't deleted
							$response['code'] = $code = 400;
							$response['message'] = ERROR_MESSAGE;
						}
					} else { // there isn't any photo in this post
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
