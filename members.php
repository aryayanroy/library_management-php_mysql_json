<?php
    session_start();
    if(!isset($_SESSION["admin"])){
        header("Location: /library-management");
        die();
    }
    require "config.php";
    $sql = $conn->prepare("SELECT username FROM admins WHERE id = ?");
    $sql->bindParam(1, $_SESSION["admin"], PDO::PARAM_STR);
    $sql->execute();
    $username = $sql->fetch(PDO::FETCH_NUM)[0];
    if($_SERVER["REQUEST_METHOD"]=="POST"){
        function sql_execute($sql, $success, $error){
            try{
                $sql->execute();
                $feedback = array(true, $success);
            }catch(PDOException $e){
                $feedback = array(false, $error);
            }
            return $feedback;
        }
        $output = array();
        if(in_array($_POST["action"], ["insert", "update"])){
            $name = trim($_POST["name"]);
            $phone = trim($_POST["phone"]);
            $email = trim($_POST["email"]);
            $address = trim($_POST["address"]);
        }
        if($_POST["action"]=="load-data"){
            $offset = ($_POST["page"]-1)*25;
            $sql = $conn->prepare("SELECT id, member_id, name, registration, renewal FROM members LIMIT ?, 25");
            $sql->bindParam(1, $offset, PDO::PARAM_INT);
            $output = sql_execute($sql, null, "Couldn't fetch records");
            if($output[0] == true){
                if($sql->rowCount()>0){
                    $output[1] = $sql->fetchAll(PDO::FETCH_NUM);
                    $sql = $conn->prepare("SELECT COUNT(*) FROM members");
                    $sql->execute();
                    $output[2] = $sql->fetch(PDO::FETCH_NUM)[0];
                }else{
                    $output[0] = false;
                    $output[1] = "No records found";
                }
            }
        }elseif($_POST["action"]=="insert"){
            $id = base_convert(time(), 10, 36);
            $sql = $conn->prepare("INSERT INTO members (member_id, name, dob, phone, email, gender, address) VALUES ('$id', ?, ?, ?, ?, ?, ?)");
            $sql->bindParam(1, $name, PDO::PARAM_STR);
            $sql->bindParam(2, $_POST["dob"], PDO::PARAM_STR);
            $sql->bindParam(3, $phone, PDO::PARAM_INT);
            $sql->bindParam(4, $email, PDO::PARAM_STR);
            $sql->bindParam(5, $_POST["gender"], PDO::PARAM_INT);
            $sql->bindParam(6, $address, PDO::PARAM_STR);
            $output = sql_execute($sql, "Data recorded successfully", "Couldn't record the data");
        }elseif($_POST["action"]=="load-view"){
            $sql = $conn->prepare("SELECT * FROM members WHERE id = ?");
            $sql->bindParam(1, $_POST["id"], PDO::PARAM_INT);
            $error = "Couldn't fetch records";
            $output = sql_execute($sql, null, $error);
            if($output[0] == true){
                if($sql->rowCount()==1){
                    $output[1] = $sql->fetch(PDO::FETCH_NUM);
                    $id = array_shift($output[1]);
                    $sql = $conn->prepare("SELECT books.title, books.isbn, borrows.issue, borrows.due FROM borrows JOIN books ON books.id = borrows.book WHERE borrows.member = ?");
                    $sql->bindParam(1, $id, PDO::PARAM_INT);
                    $output = array_merge($output, sql_execute($sql, null, $error));
                    if($output[2] == true){
                        if($sql->rowCount()>0){
                            $output[3] = $sql->fetchAll(PDO::FETCH_NUM);
                        }else{
                            $output[2] = false;
                            $output[3] = "No records found";
                        }
                    }
                }else{
                    $output[0] = false;
                    $output[1] = "Member's details not found";
                }
            }
        }elseif($_POST["action"]=="load-edit"){
            $sql = $conn->prepare("SELECT id, name, dob, phone, email, gender, address FROM members WHERE id = ?");
            $sql->bindParam(1, $_POST["id"], PDO::PARAM_INT);
            $output = sql_execute($sql, null, "Couldn't fetch records");
            if($output[0] == true){
                if($sql->rowCount()==1){
                    $output[1] = $sql->fetch(PDO::FETCH_NUM);
                }else{
                    $output[0] = false;
                    $output[1] = "No records found";
                }
            }
        }elseif($_POST["action"]=="update"){
            $sql = $conn->prepare("UPDATE members SET name = ? , dob = ?, phone = ?, email = ?, gender = ?, address = ?  WHERE id = ?");
            $sql->bindParam(1, $name, PDO::PARAM_STR);
            $sql->bindParam(2, $_POST["dob"], PDO::PARAM_STR);
            $sql->bindParam(3, $phone, PDO::PARAM_INT);
            $sql->bindParam(4, $email, PDO::PARAM_STR);
            $sql->bindParam(5, $_POST["gender"], PDO::PARAM_INT);
            $sql->bindParam(6, $address, PDO::PARAM_STR);
            $sql->bindParam(7, $_POST["id"], PDO::PARAM_INT);
            $output = sql_execute($sql, "Record updated successfully", "Couldn't update the record");
        }elseif($_POST["action"]=="renew"){
            $sql = $conn->prepare("UPDATE members SET renewal = DATE_ADD(CURDATE(), INTERVAL ? MONTH) WHERE id = ?");
            $sql->bindParam(1, $_POST["months"], PDO::PARAM_INT);
            $sql->bindParam(2, $_POST["id"], PDO::PARAM_INT);
            $output = sql_execute($sql, "Renew date updated succesfully", "Couldn't update renew date");
        }elseif($_POST["action"]=="delete"){
            $sql = $conn->prepare("DELETE FROM members WHERE id = ?");
            $sql->bindParam(1, $_POST["id"], PDO::PARAM_INT);
            $output = sql_execute($sql, "Record deleted successfully", "Couldn't delete the record");
        }elseif($_POST["action"]=="search"){
            $search = "%".$_POST["search"]."%";
            $sql = $conn->prepare("SELECT id, member_id, name, registration, renewal FROM members WHERE name LIKE ? OR member_id = ?");
            $sql->bindParam(1, $search, PDO::PARAM_STR);
            $sql->bindParam(2, $_POST["search"], PDO::PARAM_STR);
            $output = sql_execute($sql, null, "Couldn't fetch records");
            if($output[0]==true){
                if($sql->rowCount()>0){
                    $output[1] = $sql->fetchAll(PDO::FETCH_NUM);
                }else{
                    $output[0] = false;
                    $output[1] = "No records found for: ".$_POST["search"];
                }
            }
        }
        echo json_encode($output);
        die();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Members | Library Management</title>
    <link rel="shortcut icon" href="assets/public/images/favicon.ico" type="image/x-icon">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-9ndCyUaIbzAi2FUVXJi0CjmCapSmO7SnpJef0486qhLnuZ2cdeRhO02iuK6FUUVM" crossorigin="anonymous">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Noto+Sans:wght@500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/public/css/style.css">
</head>
<body>
    <div class="container-xxl">
        <div class="row">
            <aside class="d-none d-md-block col-3 col-xl-2 sticky-top vh-100 border-end">
                <div class="py-3 d-flex flex-column h-100">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 55 64" width=50 class="mx-auto"><path d="M55 26.5v23.8c0 1.2-.4 2.2-1.3 3.2-.9.9-1.9 1.5-3.2 1.6-3.5.4-6.8 1.3-10.1 2.6S34 60.8 31 62.9a6.06 6.06 0 0 1-3.5 1.1 6.06 6.06 0 0 1-3.5-1.1c-3-2.1-6.1-3.9-9.4-5.2s-6.7-2.2-10.1-2.6c-1.3-.2-2.3-.7-3.2-1.6-.9-1-1.3-2-1.3-3.2V26.5c0-1.3.5-2.4 1.4-3.2s2-1.2 3.1-1c4 .6 8 2 11.9 4 3.9 2.1 7.6 4.8 11.1 8.1 3.5-3.3 7.2-6 11.1-8.1s7.9-3.4 11.9-4c1.2-.2 2.2.1 3.1 1 .9.8 1.4 1.9 1.4 3.2z" fill="#004d40"/><path d="M39.5 11.8c0 3.3-1.1 6.1-3.4 8.4s-5.1 3.4-8.4 3.4-6.1-1.1-8.4-3.4-3.4-5.1-3.4-8.4 1.1-6.1 3.4-8.4S24.4 0 27.7 0s6.1 1.1 8.4 3.4 3.4 5.1 3.4 8.4z" fill="#e65100"/></svg>
                    <hr>
                    <nav class="nav nav-pills flex-column flex-grow-1">
                        <a href="dashboard" class="nav-link link-dark"><i class="fa-solid fa-gauge me-2"></i><span>Dashboard</span></a>
                        <a href="borrows" class="nav-link link-dark"><i class="fa-solid fa-list-check me-2"></i><span>Borrows</span></a>
                        <a href="books" class="nav-link link-dark"><i class="fa-solid fa-book me-2"></i><span>Books</span></a>
                        <a href="members" class="nav-link active"><i class="fa-solid fa-users me-2"></i><span>Members</span></a>
                        <a href="genres" class="nav-link link-dark"><i class="fa-solid fa-sitemap me-2"></i><span>Genres</span></a>
                    </nav>
                    <hr>
                    <div class="dropup-start dropup">
                        <button type="button" class="btn w-100 dropdown-toggle text-truncate" data-bs-toggle="dropdown">Hello, <?php echo $username; ?></button>
                        <ul class="dropdown-menu">
                            <li><a href="logout" class="dropdown-item">Logout</a></li>
                        </ul>
                    </div>
                </div>
            </aside>
            <main class="col-md-9 col-xl-10 px-0">
                <header class="navbar bg-white border-bottom sticky-top">
                    <div class="container-fluid">
                        <a href="/" class="navbar-brand"><h3 class="mb-0"><i class="fa-solid fa-landmark me-3"></i><span class="d-none d-md-inline">Library Management</span></h3></a>
                        <button type="button" class="btn d-md-none" data-bs-toggle="offcanvas" data-bs-target="#aside-right"><i class="fa-solid fa-bars fa-xl"></i></button>
                    </div>
                </header>
                <article class="container-fluid py-3">
                    <div class="row g-3 justify-content-between mb-3">
                        <div class="col-sm-6"><button type="button" id="insert-btn" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i><span>Add Member</span></button></div>
                        <div class="col-sm-6 col-md-4">
                            <form action="#" method="post" id="search-form" class="input-group">
                                <input type="text" id="search-field" class="form-control" placeholder="Search" spellcheck="false" autocomplete="off">
                                <button type="submit" id="search-btn" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
                            </form>
                        </div>
                    </div>
                    <div><span class="text-secondary me-2">Total records found:</span><b id="records-count"></b></div>
                    <div class="table-responsive">
                        <table id="data-table" class="table table-bordered table-hover table-striped table-sm mt-1">
                            <tr>
                                <th>Sl</th>
                                <th>ID</th>
                                <th>Name</th>
                                <th>Registration</th>
                                <th>Renewal</th>
                                <th>Status</th>
                                <th colspan="4">Action</th>
                            </tr>
                        </table>
                    </div>
                    <ul id="pagination" class="pagination justify-content-center"></ul>
                </article>
            </main>
        </div>
    </div>
    <!-- off canvas -->
    <aside id="aside-right" class="offcanvas offcanvas-end">
        <div class="offcanvas-header">
            <h5 class="offcanvas-title text-truncate">Hello, <?php echo $username; ?></h5>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas"></button>
        </div>
        <div class="offcanvas-body">
            <nav class="nav nav-pills flex-column flex-grow-1">
                <a href="dashboard" class="nav-link link-dark"><i class="fa-solid fa-gauge me-2"></i><span>Dashboard</span></a>
                <a href="borrows" class="nav-link link-dark"><i class="fa-solid fa-list-check me-2"></i><span>Borrows</span></a>
                <a href="books" class="nav-link link-dark"><i class="fa-solid fa-book me-2"></i><span>Books</span></a>
                <a href="members" class="nav-link link-dark"><i class="fa-solid fa-users me-2"></i><span>Members</span></a>
                <a href="genres" class="nav-link link-dark"><i class="fa-solid fa-sitemap me-2"></i><span>Genres</span></a>
            </nav>
        </div>
        <div class="p-3 border-top">
            <a href="logout" class="btn btn-light w-100">Logout</a>
        </div>
    </aside>
    <div id="data-modal" class="modal fade">
        <div class="modal-dialog modal-fullscreen-sm-down">
            <form action="#" method="post" id="data-form" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title action-title"></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="name" class="form-label">Full name</label>
                            <input type="text" id="name" name="name" class="form-control" spellcheck="false" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="dob" class="form-label">Date of Birth</label>
                            <input type="date" id="dob" name="dob" class="form-control" max="<?php echo date("Y-m-d");?>" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="name" class="form-label">Phone number</label>
                            <input type="tel" id="phone" name="phone" class="form-control" minlength="10" maxlength="10" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="email" class="form-label">Email address</label>
                            <input type="email" id="email" name="email" class="form-control" spellcheck="false" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="gender" class="form-label">Gender</label>
                            <select id="gender" name="gender" class="form-select" required>
                                <option value="">-select-</option>
                                <option value="1">Male</option>
                                <option value="0">Female</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label for="address" class="form-label">Address</label>
                            <input type="text" id="address" name="address" class="form-control" spellcheck="false" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" id="submit-btn" class="btn btn-primary action-text"></button>
                </div>
            </form>
        </div>
    </div>
    <div id="view-modal" class="modal fade">
        <div class="modal-dialog modal-lg modal-fullscreen-sm-down">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">View</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="text-secondary">Member ID</div>
                            <div id="view-id"></div>
                        </div>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="text-secondary">Full name</div>
                            <div id="view-name" class="word-break"></div>
                        </div>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="text-secondary">Date of Birth</div>
                            <div id="view-dob" class="word-break"></div>
                        </div>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="text-secondary">Gender</div>
                            <div id="view-gender"></div>
                        </div>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="text-secondary">Registration</div>
                            <div id="view-reg"></div>
                        </div>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="text-secondary">Renewal</div>
                            <div id="view-ren" class="text-truncate"></div>
                        </div>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="text-secondary">Phone</div>
                            <div id="view-phone" class="word-break"></div>
                        </div>
                        <div class="col-sm-6 col-md-4 col-lg-3">
                            <div class="text-secondary">Email address</div>
                            <div id="view-email" class="text-truncate"></div>
                        </div>
                        <div class="col-12">
                            <div class="text-secondary">Address</div>
                            <div id="view-address"></div>
                        </div>
                    </div>
                    <div class="table-responsive mt-3">
                        <table id="view-table" class="table table-bordered table-striped table-sm">
                            <tr>
                                <th>Sl</th>
                                <th>Title</th>
                                <th>ISBN</th>
                                <th>Issued</th>
                                <th>Due</th>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div id="renew-modal" class="modal fade">
        <div class="modal-dialog modal-sm modal-fullscreen-sm-down">
            <form action="#" method="post" id="renew-form" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Renew</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label for="renew-months" class="form-label">No. of months?</label>
                    <select id="renew-months" name="months" class="form-select">
                        <option value="">-select-</option>
                        <option value="0">0 month</option>
                        <option value="1">1 month</option>
                        <option value="6">6 months</option>
                        <option value="12">12 months</option>
                    </select>
                </div>
                <div class="modal-footer">
                    <button type="submit" id="renew-btn" class="btn btn-primary w-100">Renew</button>
                </div>
            </form>
        </div>
    </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
<script>
$(document).ready((function(){var t=window.location.href,e="<i class='fas fa-circle-notch fa-spin fa-xl'></i>";function a(t,e,a,n,r,d,l){var o;o=moment(d).diff(moment(r),"days")>0?"text-success'>Active":"text-danger'>Expired",r=moment(r).format("DD-MM-YYYY"),d=moment(d).format("DD-MM-YYYY"),t.append("<tr><td class='text-center'>"+(i+e)+"</td><td>"+a.toUpperCase()+"</td><td>"+n+"</td><td class='text-center text-nowrap'>"+r+"</td><td class='text-center text-nowrap'>"+d+"</td><td class='text-center "+o+"</td><td class='text-center'><button type='button' class='btn btn-primary btn-sm view-btn' value='"+l+"'><i class='fa-regular fa-eye'></i></button></td><td class='text-center'><button type='button' class='btn btn-primary btn-sm edit-btn' value='"+l+"'><i class='fa-solid fa-pen-to-square'></i></button></td><td class='text-center'><button type='button' class='btn btn-primary btn-sm renew-btn' value='"+l+"'><i class='fa-regular fa-calendar-plus'></i></button></td><td class='text-center'><button type='button' class='btn btn-danger btn-sm delete-btn' value="+l+"><i class='fa-solid fa-trash'></i></button></td></tr>")}function n(n){n||(n=$(".active[data-page]").data("page")?$(".active[data-page]").data("page"):1);var r=$("#data-table");r.find("tr:not(:first-child)").remove(),r.append("<tr><td colspan='9' class='text-center'>"+e+"</td></tr>"),$.post(t,{action:"load-data",page:n}).always((function(){r.find("tr:nth-child(2)").remove()})).done((function(t){var e=JSON.parse(t),d=e[2],l=25*(n-1)+1,o=$("#records-count");if(1==e[0]){var s=e[1];for(i=0;i<s.length;i++){var c=s[i];a(r,l,c[1],c[2],c[3],c[4],c[0])}o.html(d);var m=Math.ceil(d/25);for($("#pagination>*").remove(),i=1;i<=m;i++)$("#pagination").append("<li class='page-item'><a href='#' class='page-link' data-page="+i+">"+i+"</a></li>");$("[data-page='"+n+"']").addClass("active")}else r.append("<tr><td colspan='9' class='text-center'>"+e[1]+"</td></tr>"),o.html(0)}))}function r(t,e,a){$(".action-title").html(e),$("#submit-btn").html(a).data("action",t),$("#data-modal").modal("show")}function r(t,e,a){$(".action-title").html(e),$("#submit-btn").html(a).data("action",t),$("#data-modal").modal("show")}n(),$(document).on("click",".page-link",(function(t){t.preventDefault(),n($(this).data("page"))})),$("#insert-btn").click((function(){$("#data-form")[0].reset(),r("insert","New record","Add record")})),$(document).on("click",".edit-btn",(function(){var a=$(this),n=a.html();a.prop("disabled",!0).html(e);var i=$(this).val();$.post(t,{action:"load-edit",id:i}).always((function(){a.prop("disabled",!1).html(n)})).done((function(t){var e=JSON.parse(t);if(1==e[0]){t=e[1];r("update","Update record","Update"),$("#submit-btn").data("id",t[0]),$("#name").val(t[1]),$("#dob").val(t[2]),$("#phone").val(t[3]),$("#email").val(t[4]),$("#gender>option[value="+t[5]+"]").prop("selected",!0),$("#address").val(t[6])}else alert(e[1])}))})),$("#data-form").submit((function(a){a.preventDefault();var i=$("#submit-btn"),r=i.html();i.prop("disabled",!0).html(e);var d=i.data("action"),l=$(this).serializeArray();l.push({name:"action",value:d}),"update"==d&&l.push({name:"id",value:i.data("id")}),l=$.param(l),$.post(t,l).always((function(){i.prop("disabled",!1).html(r)})).done((function(t){var e=JSON.parse(t);1==e[0]&&($("#data-form")[0].reset(),$("#data-modal").modal("hide"),n()),alert(e[1])}))})),$(document).on("click",".renew-btn",(function(){$("#renew-btn").data("id",$(this).val()),$("#renew-modal").modal("show")})),$("#renew-form").submit((function(a){a.preventDefault();var i=$("#renew-btn"),r=i.html();i.prop("disabled",!0).html(e),$.post(t,{action:"renew",id:i.data("id"),months:$("#renew-months").val()}).always((function(){i.prop("disabled",!1).html(r)})).done((function(t){var e=JSON.parse(t);1==e[0]&&($("#renew-form")[0].reset(),$("#renew-modal").modal("hide"),n()),alert(e[1])}))})),$(document).on("click",".view-btn",(function(){var a=$(this),n=a.html();a.prop("disabled",!0).html(e);var r=$(this).val();$.post(t,{action:"load-view",id:r}).always((function(){a.prop("disabled",!1).html(n)})).done((function(t){var e=JSON.parse(t);if(1==e[0]){var a,n=e[1];$("#view-id").html(n[0].toUpperCase()),$("#view-name").html(n[1]),$("#view-dob").html(moment(n[2]).format("DD-MM-YYYY")),a=n[5]?"Male":"Female",$("#view-gender").html(a),$("#view-reg").html(moment(n[7]).format("DD-MM-YYYY")),$("#view-ren").html(moment(n[8]).format("DD-MM-YYYY")),$("#view-phone").html(n[3]),$("#view-email").html(n[4]),$("#view-address").html(n[6]);var r=$("#view-table");if(r.find("tr:not(:first-child)").remove(),1==e[2]){var d=e[3];for(i=0;i<d.length;i++){var l=d[i];r.append("<tr><td class='text-center'>"+(i+1)+"</td><td>"+l[0]+"</td><td class='text-nowrap'>"+l[1]+"</td><td class='text-nowrap'>"+l[2]+"</td><td class='text-nowrap'>"+l[3]+"</td></tr>")}}else r.append("<tr><td colspan='5' class='text-center'>"+e[3]+"</td></tr>");$("#view-modal").modal("show")}else alert(e[1])}))})),$(document).on("click",".delete-btn",(function(){if(confirm("Are you sure want to delete the record? This will also delete it's all related records.")){var a=$(this),i=a.html(),r=a.val();a.prop("disabled",!0).html(e),$.post(t,{action:"delete",id:r}).always((function(){a.prop("disabled",!1).html(i)})).done((function(t){var e=JSON.parse(t);alert(e[1]),1==e[0]&&n()}))}})),$("#search-form").submit((function(r){r.preventDefault();var d=$.trim($("#search-field").val());if(""!=d){var l=$("#search-btn"),o=l.html();l.prop("disabled",!0).html(e),$.post(t,{action:"search",search:d}).done((function(t){var e=$("#data-table");e.find("tr:not(:first-child)").remove(),$("#pagination>*").remove();var n=JSON.parse(t);if(1==n[0]){var r=n[1],d=r.length;for(i=0;i<d;i++){var l=r[i];a(e,1,l[1],l[2],l[3],l[4],l[0])}$("#records-count").html(d)}else e.append("<tr><td colspan='9' class='text-center'>"+n[1]+"</td></tr>"),$("#records-count").html(0)})).always((function(){l.prop("disabled",!1).html(o)}))}else n()}))}));
</script>
</html>