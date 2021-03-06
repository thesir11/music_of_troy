<?php
/**
 * Created by PhpStorm.
 * User: marlon
 * Date: 11/21/14
 * Time: 11:17 PM
 */

include_once 'psl-config.php';

function sec_session_start() {
    $session_name = 'sec_session_id';   // Set a custom session name
    $secure = SECURE;
    // This stops JavaScript being able to access the session id.
    $httponly = true;
    // Forces sessions to only use cookies.
    if (ini_set('session.use_only_cookies', 1) === FALSE) {
        header("Location: ../error.php?err=Could not initiate a safe session (ini_set)");
//        echo("Im here");
        exit();
    }
    // Gets current cookies params.
    $cookieParams = session_get_cookie_params();
    session_set_cookie_params($cookieParams["lifetime"],
        $cookieParams["path"],
        $cookieParams["domain"],
        $secure,
        $httponly);
    // Sets the session name to the one set above.
    session_name($session_name);
    session_start();            // Start the PHP session
    session_regenerate_id();    // regenerated the session, delete the old one.
}


function checkbrute($artist_id, $mysqli) {
    // Get timestamp of current time


    $now = time();

    // All login attempts are counted from the past 2 hours.
    $valid_attempts = $now - (2 * 60 * 60);

    if ($stmt = $mysqli->prepare("SELECT time
                             FROM login_attempts
                             WHERE artist_id = ?
                            AND time > '$valid_attempts'")) {
        $stmt->bind_param('i', $artist_id);

        // Execute the prepared query.
        $stmt->execute();
        $stmt->store_result();

        // If there have been more than 5 failed logins
        if ($stmt->num_rows > 5) {
            return true;
        } else {
            return false;
        }
    }
}






function esc_url($url) {

    if ('' == $url) {
        return $url;
    }

    $url = preg_replace('|[^a-z0-9-~+_.?#=!&;,/:%@$\|*\'()\\x80-\\xff]|i', '', $url);

    $strip = array('%0d', '%0a', '%0D', '%0A');
    $url = (string) $url;

    $count = 1;
    while ($count) {
        $url = str_replace($strip, '', $url, $count);
    }

    $url = str_replace(';//', '://', $url);

    $url = htmlentities($url);

    $url = str_replace('&amp;', '&#038;', $url);
    $url = str_replace("'", '&#039;', $url);

    if ($url[0] !== '/') {
        // We're only interested in relative links from $_SERVER['PHP_SELF']
        return '';
    } else {
        return $url;
    }
}


function login($email, $password, $mysqli) {
    // Using prepared statements means that SQL injection is not possible.
    if ($stmt = $mysqli->prepare("SELECT artist_id, username, password, salt
        FROM artists
       WHERE email = ?
        LIMIT 1")) {
        $stmt->bind_param('s', $email);  // Bind "$email" to parameter.
        $stmt->execute();    // Execute the prepared query.
        $stmt->store_result();

        // get variables from result.
        $stmt->bind_result($artist_id, $username, $db_password, $salt);
        $stmt->fetch();

        // hash the password with the unique salt.
        $password = hash('sha512', $password . $salt);
        if ($stmt->num_rows == 1) {
            // If the user exists we check if the account is locked
            // from too many login attempts

            if (checkbrute($artist_id, $mysqli) == true) {
                // Account is locked
                // Send an email to user saying their account is locked
                return false;
            } else {
                // Check if the password in the database matches
                // the password the user submitted.
                if ($db_password == $password) {
                    // Password is correct!
                    // Get the user-agent string of the user.
                    $user_browser = $_SERVER['HTTP_USER_AGENT'];
                    // XSS protection as we might print this value
                    $artist_id = preg_replace("/[^0-9]+/", "", $artist_id);
                    $_SESSION['artist_id'] = $artist_id;
                    // XSS protection as we might print this value
                    $username = preg_replace("/[^a-zA-Z0-9_\-]+/",
                        "",
                        $username);
                    $_SESSION['username'] = $username;
                    $_SESSION['login_string'] = hash('sha512',
                        $password . $user_browser);
                    // Login successful.
                    return true;
                } else {
                    // Password is not correct
                    // We record this attempt in the database
                    $now = time();
                    $mysqli->query("INSERT INTO login_attempts(artist_id, time)
                                    VALUES ('$artist_id', '$now')");
                    return false;
                }
            }
        } else {
            // No user exists.
            return false;
        }
    }
}


function login_check($mysqli) {
    // Check if all session variables are set
//    return true;
    if (isset($_SESSION['artist_id'],
        $_SESSION['username'],
        $_SESSION['login_string'])) {
//        return true;
        $artist_id = $_SESSION['artist_id'];
        $login_string = $_SESSION['login_string'];
        $username = $_SESSION['username'];

        // Get the user-agent string of the user.
        $user_browser = $_SERVER['HTTP_USER_AGENT'];

        if ($stmt = $mysqli->prepare("SELECT password
                                      FROM artists
                                      WHERE artist_id = ? LIMIT 1")) {
            // Bind "$artist_id" to parameter.
            $stmt->bind_param('i', $artist_id);
            $stmt->execute();   // Execute the prepared query.
            $stmt->store_result();

            if ($stmt->num_rows == 1) {
                // If the user exists get variables from result.
                $stmt->bind_result($password);
                $stmt->fetch();
                $login_check = hash('sha512', $password . $user_browser);
                if ($login_check == $login_string) {
                    // Logged In!!!!
                    return true;
                } else {
                    // Not logged in
                    return false;
                }
            } else {
                // Not logged in
                return false;
            }
        } else {
            // Not logged in
            return false;
        }
    } else {
        // Not logged in
        return false;
    }
}


function is_admin ($mysqli, $artist_id) {

    $is_admin = false;

    if ($stmt=$mysqli->prepare("SELECT is_admin FROM artists WHERE artist_id=?") ) {
        $stmt->bind_param('i', $artist_id);
        $stmt->execute();
        $stmt->bind_result($is_admin);
        $stmt->fetch();
        $stmt->close();
    }

    return $is_admin;


}

function randString($length, $charset='ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789')
{
    $str = '';
    $count = strlen($charset);
    while ($length--) {
        $str .= $charset[mt_rand(0, $count-1)];
    }
    return $str;
}


function upload_path($filename) {
     if(is_uploaded_file($_FILES[$filename]['tmp_name']) && getimagesize($_FILES[$filename]['tmp_name']) != false) {
         $name = $_FILES[$filename]['name'];
         $maxsize = 99999999;
         $filepath = "img/upload_images/" . randString(5) . "-$name" ;
         if($_FILES[$filename]['size'] < $maxsize ) {
             if (move_uploaded_file($_FILES[$filename]['tmp_name'], $filepath)) {
                 return $filepath;
             } else {
                 throw  new Exception("Error uploading file");
             }


         } else {
             throw new Exception("File Size Error");
         }

    } else {
         throw new Exception("Unsupported Image Format!" . var_dump($_FILES));
     }
}

function upload($filename, $mysqli, $artist_id) {
    if(is_uploaded_file($_FILES[$filename]['tmp_name']) && getimagesize($_FILES[$filename]['tmp_name']) != false)
    {
        /***  get the image info. ***/
        $size = getimagesize($_FILES[$filename]['tmp_name']);
        /*** assign our variables ***/
        $type = $size['mime'];
        $imgfp = fopen($_FILES[$filename]['tmp_name'], 'rb');
        $size = $size[3];
        $name = $_FILES[$filename]['name'];
        $maxsize = 99999999;


        /***  check the file is less than the maximum file size ***/
        if($_FILES[$filename]['size'] < $maxsize )
        {
            /*** connect to db ***/
//            $dbh = new PDO("mysql:host=localhost;dbname=testblob", 'username', 'password');
//
//            /*** set the error mode ***/
//            $dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

//            var_dump($name);

            $filepath = "img/upload_images/$name";
            if (move_uploaded_file($_FILES[$filename]['tmp_name'], $filepath)) {
                if (!$mysqli->query("UPDATE artists SET artist_image_path='$filepath' WHERE artist_id = $artist_id ")) {
                    throw  new Exception("Error uploading filepath to database " . var_dump($mysqli) );
                }
            } else {
                throw  new Exception("Error uploading file");
            }
//            $rs = $mysqli->query("SELECT * FROM artist_images WHERE artist_id = $artist_id");
//
//            if ($rs->num_rows > 0) {
//                //if we already have an image for the artist, we need to update it
//                $stmt = $mysqli->prepare("UPDATE artist_images SET artist_id=?, image_type=?, image=?, image_size=?, image_name=? WHERE artist_id=$artist_id");
//
//            } else {
//                //if artist has no image, we insert it
//
//                /*** our sql query ***/
//                $stmt = $mysqli->prepare("INSERT INTO artist_images (artist_id, image_type ,image, image_size, image_name) VALUES (?, ? ,?, ?, ?)");
//            }


//            var_dump($mysqli);
//            echo "/n/n";
//            var_dump($stmt);

//            $stmt->bind_param("isbss", $artist_id, $type, $imgfp, $size, $name);


            /*** execute the query ***/
//            $stmt->execute();
        }
        else
        {
            /*** throw an exception is image is not of type ***/
            throw new Exception("File Size Error");
        }
    }
    else
    {
        // if the file is not less than the maximum allowed, print an error
        throw new Exception("Unsupported Image Format!" . var_dump($_FILES));
    }
}


?>