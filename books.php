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
        if(in_array($_POST["action"], ["load-data", "search"])){
            $statement = "WITH RECURSIVE genre_hierarchy AS (SELECT id, title, parent_genre, title AS genre_hierarchy FROM genres WHERE parent_genre IS NULL UNION ALL SELECT g.id, g.title, g.parent_genre, CONCAT_WS(',', gh.genre_hierarchy, g.title) AS genre_hierarchy FROM genres g INNER JOIN genre_hierarchy gh ON g.parent_genre = gh.id) SELECT b.id, b.title, b.authors, b.isbn, gh.genre_hierarchy FROM books b JOIN genre_hierarchy gh ON b.genre = gh.id ";
        }
        if($_POST["action"]=="load-genre"){     //Load genre
            $sql = $conn->prepare("SELECT id, title FROM genres ORDER BY title");
            $sql->execute();
            if($sql->rowCount()>0){
                $output[0] = true;
                $output[1] = $sql->fetchAll(PDO::FETCH_NUM);
            }
        }elseif($_POST["action"]=="load-data"){     //Load data
            $offset = ($_POST["page"]-1)*25;
            $sql = $conn->prepare($statement."LIMIT ?, 25");
            $sql->bindParam(1, $offset, PDO::PARAM_INT);
            $output = sql_execute($sql, null, "Couldn't fetch records");
            if($output[0] = true){
                if($sql->rowCount()>0){
                    $output[1] = $sql->fetchAll(PDO::FETCH_NUM);
                    foreach($output[1] as &$row){
                        $genres = explode(",", $row[4]);
                        foreach($genres as &$genre){
                            $genre = strtoupper(substr($genre, 0, 3));
                        }
                        $row[4] = implode("-", $genres);
                    }
                    $sql = $conn->prepare("SELECT COUNT(*) FROM books");
                    $sql->execute();
                    $output[2] = $sql->fetch(PDO::FETCH_NUM)[0];
                }else{
                    $output[0] = false;
                    $output[1] = "No records found";
                }
            }
        }elseif($_POST["action"]=="insert"){    //Insert record
            $sql = $conn->prepare("INSERT INTO books (title, authors, isbn, genre) VALUES (?, ?, ?, ?)");
            $sql->bindParam(1, $_POST["title"], PDO::PARAM_STR);
            $sql->bindParam(2, $_POST["authors"], PDO::PARAM_STR);
            $sql->bindParam(3, $_POST["isbn"], PDO::PARAM_STR);
            $sql->bindParam(4, $_POST["genre"], PDO::PARAM_INT);
            $output = sql_execute($sql, "Data recorded successfully", "Couldn't record the data");
        }elseif($_POST["action"]=="load-edit"){
            $sql = $conn->prepare("SELECT * FROM books WHERE id = ?");
            $sql->bindParam(1, $_POST["id"], PDO::PARAM_INT);
            $output = sql_execute($sql, null, "Couldn't load the record");
            if($output[0]=true){
                $output = $sql->fetch(PDO::FETCH_NUM);
            }
        }elseif($_POST["action"]=="update"){
            $sql = $conn->prepare("UPDATE books SET title = ?, authors = ?, isbn = ?, genre = ? WHERE id = ?");
            $sql->bindParam(1, $_POST["title"], PDO::PARAM_STR);
            $sql->bindParam(2, $_POST["authors"], PDO::PARAM_STR);
            $sql->bindParam(3, $_POST["isbn"], PDO::PARAM_STR);
            $sql->bindParam(4, $_POST["genre"], PDO::PARAM_INT);
            $sql->bindParam(5, $_POST["id"], PDO::PARAM_INT);
            $output = sql_execute($sql, "Record updated successfully", "Couldn't update the record");
        }elseif($_POST["action"]=="delete"){
            $sql = $conn->prepare("DELETE FROM books WHERE id = ?");
            $sql->bindParam(1, $_POST["id"], PDO::PARAM_INT);
            $output = sql_execute($sql, "Record deleted successfully", "Couldn't delete the record");
        }elseif($_POST["action"]=="search"){
            $search = "%".$_POST["search"]."%";
            $sql = $conn->prepare($statement."WHERE CONCAT(b.title,b.authors,b.isbn) LIKE ?");
            $sql->bindParam(1, $search, PDO::PARAM_STR);
            $output = sql_execute($sql, null, "Couldn't fetch records");
            if($output[0]==true){
                if($sql->rowCount()>0){
                    $output[1] = $sql->fetchAll(PDO::FETCH_NUM);
                    foreach($output[1] as &$row){
                        $genres = explode(",", $row[4]);
                        foreach($genres as &$genre){
                            $genre = strtoupper(substr($genre, 0, 3));
                        }
                        $row[4] = implode("-", $genres);
                    }
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
    <title>Books | Library Management</title>
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
                        <a href="books" class="nav-link active"><i class="fa-solid fa-book me-2"></i><span>Books</span></a>
                        <a href="members" class="nav-link link-dark"><i class="fa-solid fa-users me-2"></i><span>Members</span></a>
                        <a href="shelves" class="nav-link link-dark"><i class="fa-solid fa-layer-group me-2"></i><span>Shelves</span></a>
                        <a href="genres" class="nav-link link-dark"><i class="fa-solid fa-sitemap me-2"></i><span>Genres</span></a>
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
                        <div class="col-sm-6"><button type="button" id="insert-btn" class="btn btn-primary"><i class="fa-solid fa-plus me-2"></i><span>Add book</span></button></div>
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
                                <th>Title</th>
                                <th>Author(s)</th>
                                <th>ISBN</th>
                                <th>Availability</th>
                                <th>Call Number</th>
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
                            <label for="isbn" class="form-label">ISBN</label>
                            <input type="text" id="isbn" name="isbn" class="form-control" placeholder="XX-XXXX-XXX-X" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="title" class="form-label">Title</label>
                            <input type="text" id="title" name="title" class="form-control" spellcheck="false" autocomplete="off" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="authors" class="form-label">Author(s)</label>
                            <input type="text" id="authors" name="authors" class="form-control" spellcheck="false" autocomplete="off" required>
                        </div>
                        <div class="col-sm-6">
                            <label for="genre" class="form-label">Genres</label>
                            <select id="genre" name="genre" class="form-select" required>
                            </select>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="submit" id="submit-btn" class="btn btn-primary"></button>
                </div>
            </form>
        </div>
    </div>
</body>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js" integrity="sha384-geWF76RCwLtnZ8qwWowPQNguL3RmwHVBC9FhGdlKrxdiJJigb/j/68SIy3Te4Bkz" crossorigin="anonymous"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.0/jquery.min.js"></script>
<script>
$(document).ready(function(){var c=window.location.href,p="<i class='fas fa-circle-notch fa-spin fa-xl'></i>";function u(t,a,e,n,o,d,l){t.append("<tr><td class='text-center'>"+(i+a)+"</td><td>"+e+"</td><td>"+n+"</td><td class='text-nowrap'>"+o+"</td><td></td><td class='text-nowrap'>"+d+"</td><td class='text-center'><button type='button' class='btn btn-primary btn-sm edit-btn' value='"+l+"'><i class='fa-solid fa-pen'></i></button></td><td class='text-center'><button type='button' class='btn btn-danger btn-sm delete-btn' value="+l+"><i class='fa-solid fa-trash'></i></button></td></tr>")}function o(r){r=r||($(".active[data-page]").data("page")?$(".active[data-page]").data("page"):1);var s=$("#data-table");s.find("tr:not(:first-child)").remove(),s.append("<tr><td colspan='7' class='text-center'>"+p+"</td></tr>"),$.post(c,{action:"load-data",page:r}).always(function(){s.find("tr:nth-child(2)").remove()}).done(function(t){var a=JSON.parse(t),t=a[2],e=25*(r-1)+1;if(1==a[0]){var n=a[1];for(i=0;i<n.length;i++){var o=n[i];u(s,e,o[1],o[2],o[3],o[4],o[0])}$("#records-count").html(t);var d=Math.ceil(t/25),l="";for(i=1;i<=d;i++)l+="<li class='page-item'><a href='#' class='page-link' data-page="+i+">"+i+"</a></li>";$("#pagination").html(l),$("[data-page='"+r+"']").addClass("active")}else s.append("<tr><td colspan='7' class='text-center'>"+a[1]+"</td></tr>"),$("#records-count").html(0)})}function n(t,o,d,l,r){var a=t.html();t.prop("disabled",!0).html(p),$.post(c,{action:"load-genre"}).always(function(){t.prop("disabled",!1).html(a)}).done(function(t){t=JSON.parse(t);if(1==t[0]){var a=$("#genre");a.find("option").remove(),"insert"==o&&a.append("<option value=''>-select-</option>");var e=t[1];for(i=0;i<e.length;i++){var n=e[i],n="<option value='"+n[0]+"'>"+n[1]+"</option>";a.append(n)}$("#genre option[value="+r+"]").prop("selected",!0),$(".action-title").html(d),$("#submit-btn").html(l).data("action",o),$("#data-modal").modal("show")}else alert(t[1])})}o(),$(document).on("click",".page-link",function(t){t.preventDefault(),o($(this).data("page"))}),$("#insert-btn").click(function(){$("#data-form")[0].reset(),n($(this),"insert","New record","Add record")}),$(document).on("click",".edit-btn",function(){var a=$(this),t=a.html();a.prop("disabled",!0).html(p);var e=a.val();$.post(c,{action:"load-edit",id:e}).always(function(){a.prop("disabled",!1).html(t)}).done(function(t){t=JSON.parse(t);n(a,"update","Edit record","Update",t[4]),$("#submit-btn").data("id",t[0]),$("#isbn").val(t[3]),$("#title").val(t[1]),$("#authors").val(t[2])})}),$("#data-form").submit(function(t){t.preventDefault();var a=$("#submit-btn"),e=a.html();a.prop("disabled",!0).html(p);var n=a.data("action"),t=$(this).serializeArray();t.push({name:"action",value:n}),"update"==n&&t.push({name:"id",value:a.data("id")}),t=$.param(t),$.post(c,t).always(function(){a.prop("disabled",!1).html(e)}).done(function(t){t=JSON.parse(t);1==t[0]&&($("#data-form")[0].reset(),$("#data-modal").modal("hide"),o()),alert(t[1])})}),$(document).on("click",".delete-btn",function(){var t=$(this),a=t.html(),e=t.val();confirm("Are you sure want to delete the record? This will also delete it's all related records.")&&(t.prop("disabled",!0).html(p),$.post(c,{action:"delete",id:e}).always(function(){t.prop("disabled",!1).html(a)}).done(function(t){t=JSON.parse(t);alert(t[1]),1==t[0]&&o()}))}),$("#search-form").submit(function(t){t.preventDefault();var a,e,t=$.trim($("#search-field").val());""!=t?(a=$("#search-btn"),e=a.html(),a.prop("disabled",!0).html(p),$.post(c,{action:"search",search:t}).done(function(t){var a=$("#data-table");a.find("tr:not(:first-child)").remove();t=JSON.parse(t);if(1==t[0]){var e=t[1],n=e.length;for(i=0;i<n;i++){var o=e[i];u(a,1,o[1],o[2],o[3],o[4],o[0])}$("#records-count").html(n)}else a.append("<tr><td colspan='7' class='text-center'>"+t[1]+"</td></tr>"),$("#records-count").html(0)}).always(function(){a.prop("disabled",!1).html(e)})):o()})});
</script>
</html>