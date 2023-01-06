<?php
	include 'utils/constants.php';
	include 'utils/db.php';
	include 'utils/functions.php';

	$method = $_SERVER['REQUEST_METHOD'];
	$response = [];
	$code = 200;

	function getUserList($user_name = NULL) {
		global $db;
		$response_temp = [];

		if ($user_name == NULL) {
			$query = 'SELECT * FROM users ORDER BY RAND()';
		} else {
			$query = 'SELECT * FROM users WHERE user_name LIKE \'%' . $user_name . '%\'';
		}

		$users = $db->prepare($query);
		$users->execute();

		if ($users->rowCount() > 0) {
			$users = $users->fetchAll(PDO::FETCH_ASSOC);

			for ($i = 0; $i < count($users); $i++) { // get followers and following list & hide passwords and emails
				$users[$i] = getUserModel($db, $users[$i]['user_id'], 1);
			}

			$response_temp['code'] = $code = 200;
			$response_temp['message'] = httpStatus($code);

			$response_temp['users'] = $users;
		} else {
			$response_temp['code'] = $code = 404;
			$response_temp['message'] = ERROR_NOT_FOUND;
		}

		return $response_temp;
	}

	if (isset($_SERVER['PHP_AUTH_USER'])) {
		if ($_SERVER['PHP_AUTH_USER'] == AUTH_USER && $_SERVER['PHP_AUTH_PW'] == AUTH_PW) {
			if ($method == "GET") {
				if (isset($_GET['user_name']) && !empty($_GET['user_name'])) { // filter users by name
					$response = getUserList(trim($_GET['user_name']));
				} else if (isset($_GET['user_id']) && !empty($_GET['user_id'])) { // get user details by id
					if (getUserModel($db, $_GET['user_id']) != 0) {
						$response['code'] = $code = 200;
						$response['message'] = httpStatus($code);

						$response['user'] = getUserModel($db, $_GET['user_id'], 1);
					} else {
						$response['code'] = $code = 404;
						$response['message'] = ERROR_NOT_FOUND_USER;
					}
				} else { // get all users
					$response = getUserList();
				}
			} else if ($method == "POST") {
				if (isset($_POST['user_name']) && !empty(trim($_POST['user_name'])) &&
				isset($_POST['user_password']) && !empty(trim($_POST['user_password']))) {
					$user_name = trim($_POST['user_name']);
					$user_password = $_POST['user_password'];

					if (isset($_POST['user_email']) && !empty(trim($_POST['user_email']))) { // sign up
						$user_email = trim($_POST['user_email']);

						if (filter_var($user_email, FILTER_VALIDATE_EMAIL)) { // email validation
							if(preg_match('/^[a-zA-Z0-9]{3,25}$/', $user_name)) { // username validation => english chars + numbers only & 3-25 characters
								$check_email = $db->prepare("SELECT * FROM users WHERE user_email = :user_email");
								$check_email->execute(array(
									":user_email" => $user_email
								));

								if ($check_email->rowCount() == 0) {
									$check_username = $db->prepare("SELECT * FROM users WHERE user_name = :user_name");
									$check_username->execute(array(
										":user_name" => $user_name
									));

									if ($check_username->rowCount() == 0) { // create account if username and email were not taken by someone else
										$signup = $db->prepare("INSERT INTO users (user_email, user_name, user_password) VALUES (:user_email, :user_name, :user_password)");
										$signup->execute(array(
											":user_email" => $user_email,
											":user_name" => $user_name,
											":user_password" => md5($user_password)
										));

										if ($signup->rowCount() > 0) {
											$response['code'] = $code = 200;
											$response['message'] = INFO_REGISTERED;

											$response['user'] = getUserModel($db, $db->lastInsertId());
										} else {
											$response['code'] = $code = 400;
											$response['message'] = ERROR_MESSAGE;
										}
									} else {
										$response['code'] = $code = 400;
										$response['message'] = ERROR_USERNAME_TAKEN;
									}
								} else {
									$response['code'] = $code = 400;
									$response['message'] = ERROR_EMAIL_TAKEN;
								}
							} else {
								$response['code'] = $code = 400;
								$response['message'] = ERROR_NOT_VALID_USERNAME;
							}
						} else {
							$response['code'] = $code = 400;
							$response['message'] = ERROR_NOT_VALID_EMAIL;
						}
					} else { // sign in
						$signin = $db->prepare("SELECT * FROM users WHERE user_name = :user_name AND user_password = :user_password");
						$signin->execute(array(
							":user_name" => $user_name,
							":user_password" => md5($user_password)
						));

						if ($signin->rowCount() > 0) {
							$response['code'] = $code = 200;
							$response['message'] = httpStatus($code);

							$response['user'] = getUserModel($db, $signin->fetchAll(PDO::FETCH_ASSOC)[0]['user_id']);
						} else {
							$response['code'] = $code = 404;
							$response['message'] = ERROR_LOGIN;
						}
					}
				} else {
					if (isset($_POST['user_id']) && !empty(trim($_POST['user_id']))) { // update profile photo
						$user_id = $_POST['user_id'];

						if (getUserModel($db, $user_id) == 0) { // check user id
							$response['code'] = $code = 404;
							$response['message'] = ERROR_NOT_FOUND_USER;
						} else {
							if (isset($_FILES['image'])) { // change photo
								$image = $_FILES['image'];
								$error = $image['error'];

								if ($error != 0) {
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
										$new_path = DIR_PROFILE_PHOTOS . $new_name; // profile_photos/name.extension

										if ($image_extension == 'jpg' || $image_extension == 'jpeg' || $image_extension == 'png') {
											if (move_uploaded_file($image['tmp_name'], $new_path)) {
												// get old photo's name before update
												$old_photo = $db->prepare("SELECT user_photo FROM users WHERE user_id = :user_id");
												$old_photo->execute(array(
													":user_id" => $_POST['user_id']
												));
												$old_photo = $old_photo->fetchAll(PDO::FETCH_ASSOC)[0]['user_photo'];

												$update_photo = $db->prepare("UPDATE users SET user_photo = :user_photo WHERE user_id = :user_id");
												$update_photo->execute(array(
													":user_photo" => $new_name,
													":user_id" => $user_id
												));

												if ($update_photo->rowCount() > 0) {
													$response['code'] = $code = 200;
													$response['message'] = INFO_UPDATED;

													$response['user'] = getUserModel($db, $user_id);

													if ($old_photo != DEFAULT_PHOTO) { // delete old photo if db update is completed
														unlink(DIR_PROFILE_PHOTOS . $old_photo);
													}
												} else { // delete photo if db update is not completed
													$response['code'] = $code = 400;
													$response['message'] = ERROR_MESSAGE;

													unlink($new_path);
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
							} else if (isset($_POST['image_name']) && !empty(trim($_POST['image_name']))) { // delete photo
								$remove_photo = $db->prepare("UPDATE users SET user_photo = :user_photo WHERE user_id = :user_id");
								$remove_photo->execute(array(
									":user_photo" => DEFAULT_PHOTO,
									":user_id" => $user_id
								));

								if ($remove_photo->rowCount() > 0) {
									unlink(DIR_PROFILE_PHOTOS . $_POST['image_name']);

									$response['code'] = $code = 200;
									$response['message'] = INFO_DELETED;

									$response['user'] = getUserModel($db, $user_id);
								} else {
									$response['code'] = $code = 400;
									$response['message'] = ERROR_MESSAGE;
								}
							} else { // bad request
								$response['code'] = $code = 400;
								$response['message'] = httpStatus($code);
							}
						}
					} else { // bad request
						$response['code'] = $code = 400;
						$response['message'] = httpStatus($code);
					}
				}
			} else if ($method == "PUT") {
				$data = json_decode(file_get_contents("php://input"));

				if (isset($data->user_id) && !empty(trim($data->user_id)) &&
				isset($data->user_email) && !empty(trim($data->user_email)) &&
				isset($data->user_name) && !empty(trim($data->user_name)) &&
				isset($data->user_password) && isset($data->user_profile_private) && ($data->user_profile_private == 0 || $data->user_profile_private == 1)) { // update fields
					if (filter_var($data->user_email, FILTER_VALIDATE_EMAIL)) { // email validation
						if (getUserModel($db, $data->user_id) != 0) { // check user id
							$parameters = [
								":user_email" => trim($data->user_email),
								":user_fullname" => ($data->user_fullname == NULL || empty($data->user_fullname)) ? NULL : trim($data->user_fullname),
								":user_bio" => ($data->user_bio == NULL || empty($data->user_bio)) ? NULL : trim($data->user_bio),
								":user_profile_private" => trim($data->user_profile_private),
								":user_id" => trim($data->user_id)
							];

							if (empty(trim($data->user_password))) {
								$query =  "UPDATE users SET user_email = :user_email, user_fullname = :user_fullname, user_bio = :user_bio, user_profile_private = :user_profile_private WHERE user_id = :user_id";
							} else {
								$query =  "UPDATE users SET user_email = :user_email, user_password = :user_password, user_fullname = :user_fullname, user_bio = :user_bio, user_profile_private = :user_profile_private WHERE user_id = :user_id";
								$parameters[':user_password'] = md5($data->user_password);
							}

							$update_user = $db->prepare($query);
							$update_user->execute($parameters);

							if ($update_user->rowCount() > 0) {
								$response['code'] = $code = 200;
								$response['message'] = INFO_UPDATED;

								$response['user'] = getUserModel($db, $data->user_id);
							} else {
								$response['code'] = $code = 400;
								$response['message'] = ERROR_MESSAGE;
							}
						} else {
							$response['code'] = $code = 404;
							$response['message'] = ERROR_NOT_FOUND_USER;
						}
					} else {
						$response['code'] = $code = 400;
						$response['message'] = ERROR_NOT_VALID_EMAIL;
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
