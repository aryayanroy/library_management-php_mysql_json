<?php
    session_start();
    if(!isset($_SESSION["admin"])){
        header("Location: /library-management");
        die();
    }

    require "config.php";

    //Get admin username
    $sql = $conn->prepare("SELECT username FROM admins WHERE id = ?");
    $sql->bindParam(1, $_SESSION["admin"], PDO::PARAM_STR);
    $sql->execute();
    $username = $sql->fetch(PDO::FETCH_NUM)[0];

    if($_SERVER["REQUEST_METHOD"]=="POST" && isset($_POST["action"])){
        function sql_execute($sql, $success, $error){
            try{
                $sql->execute();
                $feedback = array(true, $success);
            }catch(PDOException $e){
                $feedback = array(false, $error);
            }
            return $feedback;
        }
        if($_POST["action"]=="submit"){
            $genre = trim($_POST["genre"]);
            if($_POST["parent-genre"]==NULL){
                $sql = $conn->prepare("INSERT INTO genres (title) VALUES (?)");
            }else{
                $sql = $conn->prepare("INSERT INTO genres (title, parent_genre) VALUES (?, ?)");
                $sql->bindParam(2, $_POST["parent-genre"], PDO::PARAM_INT);
            }
            $sql->bindParam(1, $genre, PDO::PARAM_STR);
            $feedback = sql_execute($sql, "Data recorded successfully", "Couldn't record the data");
        }else if($_POST["action"]=="load-genre"){
            $sql = $conn->prepare("SELECT id, title FROM genres");
            $sql->execute();
            if($sql->rowCount()>0){
                $feedback = array(true, $sql->fetchAll(PDO::FETCH_NUM));
            }else{
                $feedback = array(false);
            }
        }else if($_POST["action"]=="load-data"){
            $offset = ($_POST["page"]-1)*$_POST["rpp"];
            $search = "%".trim($_POST["search"])."%";
            $sql = $conn->prepare("SELECT * FROM genres WHERE title LIKE ? LIMIT ?, ?");
            $sql->bindParam(1, $search, PDO::PARAM_STR);
            $sql->bindParam(2, $offset, PDO::PARAM_INT);
            $sql->bindParam(3, $_POST["rpp"], PDO::PARAM_INT);
            $sql->execute();
            if($sql->rowCount()>0){
                $rows = array();
                while ($row = $sql->fetch(PDO::FETCH_NUM)){
                    $sql2 = $conn->prepare("SELECT * FROM genres WHERE parent_genre = ?");
                    $sql2->bindParam(1, $row[0], PDO::PARAM_INT);
                    $sql2->execute();
                    if($sql2->rowCount()==0){
                        $row[3]=true;
                    }else{
                        $row[3]=false;
                    }
                    if($row[2]!=null){
                        $sql2 = $conn->prepare("SELECT title FROM genres WHERE id = ?");
                        $sql2->bindParam(1, $row[2], PDO::PARAM_INT);
                        $sql2->execute();
                        $row[2] = $sql2->fetch(PDO::FETCH_NUM)[0];
                    }
                    array_push($rows, $row);
                }
                $sql = $conn->prepare("SELECT * FROM genres WHERE title LIKE ?");
                $sql->bindParam(1, $search, PDO::PARAM_STR);
                $sql->execute();
                $count = $sql->rowCount();
                $feedback = array(true, $rows, $count);
            }else{
                $feedback = array(false, "No records found");
            }
        }else if($_POST["action"]=="edit"){
            $id = $_POST["id"];
            $title = trim($_POST["title"]);
            $sql = $conn->prepare("UPDATE genres SET title = ? WHERE id = ?");
            $sql->bindParam(1, $title, PDO::PARAM_STR);
            $sql->bindParam(2, $id, PDO::PARAM_INT);
            $feedback = sql_execute($sql, "Record updated successfully", "Couldn't update the record successfully");
        }else if($_POST["action"]=="delete"){
            $id = $_POST["id"];
            $sql = $conn->prepare("DELETE FROM genres WHERE id = ?");
            $sql->bindParam(1, $id, PDO::PARAM_INT);
            $feedback = sql_execute($sql, "Record deleted successfully", "Couldn't delete the record");
        }
        echo json_encode($feedback);
        die();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Genres | Library Management</title>
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
            <aside class="d-none d-md-block col-3 col-xl-2 min-vh-100 border-end">
                <div class="py-3 d-flex flex-column sticky-top h-100">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 55 64" width=50 class="mx-auto"><path d="M55 26.5v23.8c0 1.2-.4 2.2-1.3 3.2-.9.9-1.9 1.5-3.2 1.6-3.5.4-6.8 1.3-10.1 2.6S34 60.8 31 62.9a6.06 6.06 0 0 1-3.5 1.1 6.06 6.06 0 0 1-3.5-1.1c-3-2.1-6.1-3.9-9.4-5.2s-6.7-2.2-10.1-2.6c-1.3-.2-2.3-.7-3.2-1.6-.9-1-1.3-2-1.3-3.2V26.5c0-1.3.5-2.4 1.4-3.2s2-1.2 3.1-1c4 .6 8 2 11.9 4 3.9 2.1 7.6 4.8 11.1 8.1 3.5-3.3 7.2-6 11.1-8.1s7.9-3.4 11.9-4c1.2-.2 2.2.1 3.1 1 .9.8 1.4 1.9 1.4 3.2z" fill="#004d40"/><path d="M39.5 11.8c0 3.3-1.1 6.1-3.4 8.4s-5.1 3.4-8.4 3.4-6.1-1.1-8.4-3.4-3.4-5.1-3.4-8.4 1.1-6.1 3.4-8.4S24.4 0 27.7 0s6.1 1.1 8.4 3.4 3.4 5.1 3.4 8.4z" fill="#e65100"/></svg>
                    <hr>
                    <nav class="nav nav-pills flex-column flex-grow-1">
                        <a href="dashboard" class="nav-link link-dark"><i class="fa-solid fa-gauge me-2"></i><span>Dashboard</span></a>
                        <a href="borrows" class="nav-link link-dark"><i class="fa-solid fa-list-check me-2"></i><span>Borrows</span></a>
                        <a href="books" class="nav-link link-dark"><i class="fa-solid fa-book me-2"></i><span>Books</span></a>
                        <a href="members" class="nav-link link-dark"><i class="fa-solid fa-users me-2"></i><span>Members</span></a>
                        <a href="shelves" class="nav-link link-dark"><i class="fa-solid fa-layer-group me-2"></i><span>Shelves</span></a>
                        <a href="genres" class="nav-link active"><i class="fa-solid fa-sitemap me-2"></i><span>Genres</span></a>
                        <a href="settings" class="nav-link link-dark"><i class="fa-solid fa-cog me-2"></i><span>Settings</span></a>
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
                        <div class="col-sm-6"><button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#add-record"><i class="fa-solid fa-plus me-2"></i><span>Add Genre</span></button></div>
                        <div class="col-sm-6 col-md-4">
                            <form action="#" method="post" id="search-form" class="input-group">
                                <input type="text" id="search-field" class="form-control" placeholder="Search" spellcheck="false" autocomplete="off">
                                <button type="submit" class="btn btn-primary"><i class="fa-solid fa-magnifying-glass"></i></button>
                            </form>
                        </div>
                    </div>
                    <div><span class="text-secondary me-2">Total records found:</span><b id="records-count"></b></div>
                    <div class="table-responsive">
                        <table id="data-table" class="table table-bordered table-hover table-striped table-sm mt-1">
                            <tr>
                                <th>Sl</th>
                                <th>Genres</th>
                                <th>Subgenre</th>
                                <th>Books</th>
                                <th colspan="2">Action</th>
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
                <a href="shelves" class="nav-link link-dark"><i class="fa-solid fa-layer-group me-2"></i><span>Shelves</span></a>
                <a href="genres" class="nav-link link-dark"><i class="fa-solid fa-sitemap me-2"></i><span>Genres</span></a>
                <a href="settings" class="nav-link link-dark"><i class="fa-solid fa-cog me-2"></i><span>Settings</span></a>
            </nav>
        </div>
        <div class="p-3 border-top">
            <a href="logout" class="btn btn-light w-100">Logout</a>
        </div>
    </aside>
    <div id="add-record" class="modal fade">
        <div class="modal-dialog modal-fullscreen-sm-down">
            <form action="#" method="post" id="data-form" class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add Genre</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-3">
                        <div class="col-sm-6">
                            <label for="parent-genre" class="form-label">Parent Genre</label>
                            <select id="parent-genre" name="parent-genre" class="form-select">
                                <option value="">None</option>
                            </select>
                        </div>
                        <div class="col-sm-6">
                            <label for="genre" class="form-label">Genre</label>
                            <input type="text" id="genre" name="genre" class="form-control" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" id="submit-data" class="btn btn-primary">Add genre</button>
                </div>
            </form>
        </div>
    </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
<script>
$(document).ready(function(){var t=window.location.href;function e(e,a){e||(e=$(".active[data-page]").data("page")?$(".active[data-page]").data("page"):1),a||(a=""),$.post(t,{action:"load-data",page:e,rpp:3,search:a}).done(function(t){var a=JSON.parse(t);if($("#data-table tr:not(:first-child)").remove(),!0==a[0]){var n=a[1],o=a[2],r=Math.ceil(o/3),d=(e-1)*3+1;for($("#records-count").html(o),i=0;i<n.length;i++){var l,s="-",c="-";null==n[i][2]?l=n[i][1]:(l=n[i][2],s=n[i][1]),!0==n[i][3]&&(c="<button type='button' class='btn btn-danger btn-sm delete-btn' value="+n[i][0]+"><i class='fa-solid fa-trash'></i></button>"),$("#data-table").append("<tr><td class='text-center'>"+(i+d)+"</td><td>"+l+"</td><td>"+s+"</td><td></td><td class='text-center'><button type='button' class='btn btn-primary btn-sm edit-btn' value='"+n[i][0]+"' data-title='"+n[i][1]+"'><i class='fa-solid fa-pen'></i></button></td><td class='text-center'>"+c+"</td></tr>")}for($("#pagination>*").remove(),i=1;i<=r;i++)$("#pagination").append("<li class='page-item'><a href='#' class='page-link' data-page="+i+">"+i+"</a></li>"),$("[data-page='"+e+"']").addClass("active")}else $("#data-table").append("<tr><td colspan='5' class='text-center'>No records found</td></tr>"),$("#records-count").html(0)})}function a(){$("#parent-genre option:not(:first)").remove(),$.post(t,{action:"load-genre"}).done(function(t){var e=JSON.parse(t);if(!0==e[0]){var a=e[1];for(i=0;i<a.length;i++)$("#parent-genre").append("<option value='"+a[i][0]+"'>"+a[i][1]+"</option>")}})}e(),a(),$("#data-form").submit(function(n){n.preventDefault(),$("#submit-data").prop("disabled",!0).html("<i class='fas fa-spinner fa-pulse'></i>");var o=$(this).serializeArray();o.push({name:"action",value:"submit"}),o=$.param(o),$.post(t,o).done(function(t){var n=JSON.parse(t);!0==n[0]&&($("#data-form")[0].reset(),$("#add-record").modal("hide")),alert(n[1]),e(),a()}).fail(function(){alert("Unexpected error")}).always(function(){$("#submit-data").prop("disabled",!1).html("Add record")})}),$(document).on("click",".edit-btn",function(){var a=prompt("Enter the Genre title",$(this).data("title"));if(a){var n=$(this).val();""!=(a=$.trim(a))?$.post(t,{action:"edit",title:a,id:n}).done(function(t){alert(JSON.parse(t)[1]),e()}).fail(function(){alert("Unexpected error")}):alert("Title cannot be empty")}}),$(document).on("click",".delete-btn",function(){if(confirm("Do you really want to delete this record? This will also delete the book records linked to this genere.")){var a=$(this).val();$.post(t,{action:"delete",id:a}).done(function(t){alert(JSON.parse(t)[1]),e()}).fail(function(){alert("Unexpected error")})}}),$(document).on("click",".page-link",function(t){t.preventDefault(),e($(this).data("page"),null)}),$("#search-form").submit(function(t){t.preventDefault(),e(null,$("#search-field").val())})});
</script>
</html>