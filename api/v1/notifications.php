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
				if (isset($_GET['user_id']) && !empty(trim($_GET['user_id']))) { // get notifications
					$user_id = $_GET['user_id'];

					if (getUserModel($db, $user_id) != 0) { // check user id
						// get all notifications
						$notifications = $db->prepare("SELECT * FROM notifications WHERE notification_receiver = :notification_receiver ORDER BY notification_id DESC");
						$notifications->execute(array(
								":notification_receiver" => $user_id
						));

						if ($notifications->rowCount() > 0) {
							$response['code'] = $code = 200;
							$response['message'] = httpStatus($code);

							// get unseen notifications count
							$unseen_notifications = $db->prepare("SELECT * FROM notifications WHERE notification_receiver = :notification_receiver AND is_seen = 0");
							$unseen_notifications->execute(array(
								":notification_receiver" => $user_id
							));
							$response['unseen_notification_count'] = $unseen_notifications->rowCount();

							$notifications = $notifications->fetchAll(PDO::FETCH_ASSOC);

							for ($i = 0; $i < count($notifications); $i++) { // get notification resources & remove notification type and receiver
								if ($notifications[$i]['notification_type'] == 0) { // user
									$notifications[$i]['user'] = getUserModel($db, $notifications[$i]['notification_resource'], 1, 0);
									$notifications[$i]['post'] = null;
								} else { // post
									$notifications[$i]['user'] = null;
									$notifications[$i]['post'] = getPostModel($db, $notifications[$i]['notification_resource'], 0);
								}

								unset($notifications[$i]['notification_resource']);
								unset($notifications[$i]['notification_type']);
								unset($notifications[$i]['notification_receiver']);
							}

							$response['notifications'] = $notifications;
						} else { // there is nothing in here
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
			} else if ($method == "PUT") {
				$data = json_decode(file_get_contents("php://input"));

				if (isset($data->user_id) && !empty(trim($data->user_id))) { // update notifications (mark all as seen)
					if (getUserModel($db, $data->user_id) != 0) { // check user id
						$update_notifications = $db->prepare("UPDATE notifications SET is_seen = 1 WHERE notification_receiver = :notification_receiver");
						$update_notifications->execute(array(
							":notification_receiver" => $data->user_id
						));

						if ($update_notifications->rowCount() > 0) {
							$response['code'] = $code = 200;
							$response['message'] = INFO_UPDATED;
						} else {
							$response['code'] = $code = 400;
							$response['message'] = ERROR_MESSAGE;
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
				if (isset($_GET['user_id']) && !empty(trim($_GET['user_id']))) { // delete all notifications
					$user_id = $_GET['user_id'];

					if (getUserModel($db, $user_id) != 0) { // check user id
						$delete_notifications = $db->prepare("DELETE FROM notifications WHERE notification_receiver = :notification_receiver");
						$delete_notifications->execute(array(
							":notification_receiver" => $_GET['user_id']
						));

						if ($delete_notifications->rowCount() > 0) {
							$response['code'] = $code = 200;
							$response['message'] = INFO_DELETED;
						} else {
							$response['code'] = $code = 400;
							$response['message'] = ERROR_MESSAGE;
						}
					} else { // there isn't any user with this id
						$response['code'] = $code = 400;
						$response['message'] = httpStatus($code);
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
