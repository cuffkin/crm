<?php
session_start();
include_once "../../../config/db.php";

if (!isset($_SESSION['user_id'])) {
    echo '<div class="alert alert-danger">Авторизуйтесь!</div>';
    exit;
}

$sql = "SELECT id, username, email, role FROM PCRM_User WHERE deleted = 0";
$result = $conn->query($sql);
?>
<div class="table-responsive">
    <table class="table table-striped">
        <thead>
            <tr>
                <th>#</th>
                <th>Имя пользователя</th>
                <th>Email</th>
                <th>Роль</th>
                <th>Действия</th>
            </tr>
        </thead>
        <tbody>
            <?php while ($row = $result->fetch_assoc()): ?>
                <tr>
                    <td><?php echo $row['id']; ?></td>
                    <td><?php echo $row['username']; ?></td>
                    <td><?php echo $row['email']; ?></td>
                    <td><?php echo $row['role']; ?></td>
                    <td>
                        <a href="#" data-module="users/edit/<?php echo $row['id']; ?>" class="btn btn-sm btn-primary">Редактировать</a>
                        <a href="#" data-module="access/edit/<?php echo $row['id']; ?>" class="btn btn-sm btn-info">Настроить доступ</a>
                    </td>
                </tr>
            <?php endwhile; ?>
        </tbody>
    </table>
</div>