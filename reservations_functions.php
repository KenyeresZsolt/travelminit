<?php

function dateDifference($checkout, $checkin)
{
    $diff = strtotime($checkout) - strtotime($checkin);
    return ceil(abs($diff/86400));
}

function calculatePrice($input, $accmId)
{   
    $pdo = getConnection();
    $statement = $pdo->prepare(
        'SELECT *
        FROM accm_units au
        WHERE au.accm_id = ?');
    $statement->execute([$accmId]);
    $units = $statement->fetchAll(PDO::FETCH_ASSOC);

    $roomPrice = 0;

    foreach($input['rooms'] as $k => $roomsCount){
        foreach($units as $unit){
            if($unit['id'] = $k){
                $roomPrice += $unit['price']*$roomsCount;
            }
        }
    }
    
    $totalRooms = array_sum($_POST['rooms']);
    echo "<pre>";
    echo $totalRooms . "<br>";
    echo $roomPrice . "<br>";
    var_dump($_POST);
}

function reserveAccmHandler($urlParams)
{
    $reservedAccmId = $urlParams['accmId'];

    calculatePrice($_POST, $urlParams['accmId']);
    exit;

    $nights = dateDifference($_POST["checkout"], $_POST["checkin"]);
       
    $pdo = getConnection();
    $statement = $pdo->prepare(
        'SELECT *
        FROM accms a
        WHERE a.id = ?'
    );
    $statement->execute([$reservedAccmId]);
    $accm = $statement->fetch(PDO::FETCH_ASSOC);

    $totalPrice = ($_POST["guests"]*$nights*$accm['price']);

    if (empty($_POST["name"]) 
        OR empty($_POST["email"]) 
        OR empty($_POST["phone"]) 
        OR empty($_POST["guests"]) 
        OR empty($_POST["checkin"])
        OR empty($_POST["checkout"])
        OR empty($_POST["phone"])) {
        urlRedirect('szallasok/' . $accm['slug'], [
            'res' => "1",
            'info' => "emptyValue",
            'values' => base64_encode(json_encode($_POST)),
            'href' => '#infoMessage'
        ]);
        return ;
    }

    $statement = $pdo->prepare(
        'INSERT INTO reservations (name, email, phone, status, reserved, guests, checkin, checkout, nights, total_price, reserved_accm_id)
        VALUES (:name, :email, :phone, :status, :reserved, :guests, :checkin, :checkout, :nights, :total_price, :reserved_accm_id)'
    );
    $statement->execute([
        'name' => $_POST['name'],
        'email' => $_POST['email'],
        'phone' => $_POST['phone'],
        'status' => 'RESERVED',
        'reserved' => time(),
        'guests' => $_POST['guests'],
        'checkin' => $_POST['checkin'],
        'checkout' => $_POST['checkout'],
        'nights' => $nights,
        'total_price' => $totalPrice,
        'reserved_accm_id' => $reservedAccmId
    ]);

    //email
    $statement = insertMailSql();

    $body = render("res-confirm-email-template.php", [
        'name' =>  $_POST['name'] ?? NULL,
        'email' =>  $_POST['email'] ?? NULL,
        'guests' => $_POST['guests'],
        'checkin' => $_POST['checkin'],
        'checkout' => $_POST['checkout'],
        'nights' => $nights,
        'total_price' => $totalPrice,
        'accm' => $accm
    ]);

    $statement->execute([
        $_POST['email'],
        "Foglalási igazolás - " . $accm['name'] . ", " . $accm['location'],
        $body,
        'notSent',
        0,
        time()
    ]);

    header('Location: /szallasok/' . $accm['slug'] . '?info=reserved');

    sendMailsHandler();

    /*urlRedirect('szallasok/' . $accm['slug'], [
        'info' => 'reserved'
    ]);*/   //ezzel nem küldi ki az emaileket
}

function reservationListHandler()
{
    redirectToLoginIfNotLoggedIn();

    $pdo = getConnection();
    $statement = $pdo->prepare(
        'SELECT *
        FROM reservations'
    );
    $statement->execute();
    $reservations = $statement->fetchAll(PDO::FETCH_ASSOC);

    $statement = $pdo->prepare(
        'SELECT *
        FROM accms'
    );
    $statement->execute();
    $accms = $statement->fetchAll(PDO::FETCH_ASSOC);

    $heroListTemplate = render("res-list.php", [
        "reservations" => $reservations,
        "accms" => $accms,
        "updateReservationId" => $_GET["edit"] ?? NULL,
        "info" => $_GET['info'] ?? NULL,
    ]);
    echo render('wrapper.php', [
        'content' => $heroListTemplate,
        'activeLink' => '/foglalasok',
        'isAuthorized' => true,
        'isAdmin' => isAdmin(),
        'title' => "Foglalások",
        'unreadMessages' => countUnreadMessages(),
        'playChatSound' => playChatSound()
    ]);
}

function updateReservationHandler()
{
    redirectToLoginIfNotLoggedIn();

    $pdo = getConnection();
    $statement = $pdo->prepare(
        'UPDATE reservations
        SET name = ?, email = ?, phone = ?, guests = ?, checkin = ?, checkout = ?, nights = ?, total_price = ?
        WHERE id = ?'
    );
    $statement->execute([
        $_POST["name"],
        $_POST["email"],
        $_POST["phone"],
        $_POST["guests"],
        $_POST["checkin"],
        $_POST["checkout"],
        dateDifference($_POST["checkout"], $_POST["checkin"]),
        $_POST["price"],
        $_GET['id']
    ]);

    urlRedirect('foglalasok', [
        'info' => 'updated'
    ]);
}

function cancelReservationHandler()
{
    redirectToLoginIfNotLoggedIn();
    
    $pdo = getConnection();
    $statement = $pdo->prepare(
        'UPDATE reservations
        SET status = "CANCELED"
        WHERE id= ?'
    );
    $statement->execute([$_GET['id']]);

    urlRedirect('foglalasok', [
        'info' => 'canceled'
    ]);
}

function deleteReservationHandler()
{
    redirectToLoginIfNotLoggedIn();

    $pdo = getConnection();
    $statement = $pdo->prepare(
        'DELETE FROM reservations
        WHERE id = ?'
    );
    $statement->execute([$_GET['id']]);

    urlRedirect('foglalasok', [
        'info' => 'deleted'
    ]);
}

?>