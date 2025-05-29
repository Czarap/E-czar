<?php
session_start();
require_once __DIR__ . '/../includes/config.php';

// Handle form submissions first, before any output
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        $redirect = false;
        
        switch ($_POST['action']) {
            case 'add':
                $name = sanitize($_POST['name']);
                $email = sanitize($_POST['email']);
                $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                $role = sanitize($_POST['role']);
                $is_seller = isset($_POST['is_seller']) ? 1 : 0;

                // Check if email already exists
                $check_email = $conn->prepare("SELECT id FROM users WHERE email = ?");
                $check_email->bind_param("s", $email);
                $check_email->execute();
                $result = $check_email->get_result();
                
                if ($result->num_rows > 0) {
                    $_SESSION['error_message'] = "Email address already exists";
                } else {
                    $stmt = $conn->prepare("INSERT INTO users (name, email, password, role, is_seller) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("ssssi", $name, $email, $password, $role, $is_seller);
                    if($stmt->execute()) {
                        $_SESSION['success_message'] = "User added successfully";
                    } else {
                        $_SESSION['error_message'] = "Error adding user";
                    }
                }
                $redirect = true;
                break;

            case 'edit':
                $id = intval($_POST['id']);
                $name = sanitize($_POST['name']);
                $email = sanitize($_POST['email']);
                $role = sanitize($_POST['role']);
                $is_seller = isset($_POST['is_seller']) ? 1 : 0;

                // Check if email already exists for other users
                $check_email = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
                $check_email->bind_param("si", $email, $id);
                $check_email->execute();
                $result = $check_email->get_result();
                
                if ($result->num_rows > 0) {
                    $_SESSION['error_message'] = "Email address already exists";
                    $redirect = true;
                } else {
                    if (!empty($_POST['password'])) {
                        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
                        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, password=?, role=?, is_seller=? WHERE id=?");
                        $stmt->bind_param("ssssii", $name, $email, $password, $role, $is_seller, $id);
                    } else {
                        $stmt = $conn->prepare("UPDATE users SET name=?, email=?, role=?, is_seller=? WHERE id=?");
                        $stmt->bind_param("sssii", $name, $email, $role, $is_seller, $id);
                    }
                    if($stmt->execute()) {
                        $_SESSION['success_message'] = "User updated successfully";
                    } else {
                        $_SESSION['error_message'] = "Error updating user";
                    }
                }
                $redirect = true;
                break;

            case 'delete':
                $id = intval($_POST['id']);
                // Don't delete if it's the last admin
                $admin_count = $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'];
                $user_role = $conn->query("SELECT role FROM users WHERE id = $id")->fetch_assoc()['role'];
                
                if ($admin_count > 1 || $user_role !== 'admin') {
                    if($conn->query("DELETE FROM users WHERE id = $id")) {
                        $_SESSION['success_message'] = "User deleted successfully";
                    } else {
                        $_SESSION['error_message'] = "Error deleting user";
                    }
                } else {
                    $_SESSION['error_message'] = "Cannot delete the last admin user";
                }
                $redirect = true;
                break;
        }
        
        if ($redirect) {
            header('Location: admin_users.php');
            exit();
        }
    }
}

// Include header after handling form submission
require_once 'includes/admin_header.php';

// Display any messages
if (isset($_SESSION['success_message'])) {
    echo '<div class="alert alert-success">' . htmlspecialchars($_SESSION['success_message']) . '</div>';
    unset($_SESSION['success_message']);
}
if (isset($_SESSION['error_message'])) {
    echo '<div class="alert alert-danger">' . htmlspecialchars($_SESSION['error_message']) . '</div>';
    unset($_SESSION['error_message']);
}

// Get all users
$users = $conn->query("SELECT * FROM users ORDER BY created_at DESC")->fetch_all(MYSQLI_ASSOC);
?>

<div class="admin-header">
    <div class="d-flex justify-content-between align-items-center">
        <h1 class="h3 mb-0">User Management</h1>
        <button type="button" class="btn btn-sunset" data-bs-toggle="modal" data-bs-target="#addUserModal">
            <i class="bi bi-plus-lg"></i> Add New User
        </button>
    </div>
</div>

<!-- Users Table -->
<div class="card">
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Seller Status</th>
                        <th>Created</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($user['name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td>
                            <?php if($user['role'] === 'admin'): ?>
                                <span class="badge bg-danger">Admin</span>
                            <?php else: ?>
                                <span class="badge bg-info">User</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if($user['is_seller']): ?>
                                <span class="badge bg-warning">Seller</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Customer</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                        <td>
                            <button type="button" class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal<?php echo $user['id']; ?>">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <?php if ($user['role'] !== 'admin' || $conn->query("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")->fetch_assoc()['count'] > 1): ?>
                            <button type="button" class="btn btn-sm btn-danger" onclick="deleteUser(<?php echo $user['id']; ?>)">
                                <i class="bi bi-trash"></i>
                            </button>
                            <?php endif; ?>
                        </td>
                    </tr>

                    <!-- Edit User Modal -->
                    <div class="modal fade" id="editUserModal<?php echo $user['id']; ?>" tabindex="-1">
                        <div class="modal-dialog">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Edit User</h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <form action="" method="POST">
                                    <div class="modal-body">
                                        <input type="hidden" name="action" value="edit">
                                        <input type="hidden" name="id" value="<?php echo $user['id']; ?>">
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Name</label>
                                            <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($user['name']); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Email</label>
                                            <input type="email" class="form-control" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">New Password</label>
                                            <div class="password-field">
                                                <input type="password" class="form-control" name="password" placeholder="Leave blank to keep current">
                                                <span class="password-toggle-icon" onclick="togglePassword(this)">
                                                    <i class="bi bi-eye"></i>
                                                </span>
                                            </div>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <label class="form-label">Role</label>
                                            <select class="form-select" name="role" required>
                                                <option value="user" <?php echo $user['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                                                <option value="admin" <?php echo $user['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                                                <option value="seller" <?php echo $user['role'] === 'seller' ? 'selected' : ''; ?>>Seller</option>
                                            </select>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" name="is_seller" id="editIsSeller<?php echo $user['id']; ?>" <?php echo $user['is_seller'] ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="editIsSeller<?php echo $user['id']; ?>">Seller Account</label>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                        <button type="submit" class="btn btn-sunset">Update User</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content bg-dark">
            <div class="modal-header border-sunset">
                <h5 class="modal-title">Add New User</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form action="" method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="add">
                    
                    <div class="mb-3">
                        <label class="form-label">Name</label>
                        <input type="text" class="form-control bg-dark text-light" name="name" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-control bg-dark text-light" name="email" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" class="form-control bg-dark text-light" name="password" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Role</label>
                        <select class="form-select bg-dark text-light" name="role" required>
                            <option value="user">User</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <div class="form-check">
                            <input type="checkbox" class="form-check-input" name="is_seller" id="addIsSeller">
                            <label class="form-check-label" for="addIsSeller">Seller Account</label>
                        </div>
                    </div>
                </div>
                <div class="modal-footer border-sunset">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-sunset">Add User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function deleteUser(id) {
    if (confirm('Are you sure you want to delete this user?')) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.innerHTML = `
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="${id}">
        `;
        document.body.append(form);
        form.submit();
    }
}

function togglePassword(element) {
    const input = element.previousElementSibling;
    const icon = element.querySelector('i');
    
    if (input.type === 'password') {
        input.type = 'text';
        icon.classList.replace('bi-eye', 'bi-eye-slash');
    } else {
        input.type = 'password';
        icon.classList.replace('bi-eye-slash', 'bi-eye');
    }
}
</script>

<style>
/* Modal Styles */
.modal-content {
    background: linear-gradient(145deg, var(--sunset-darker), var(--sunset-dark));
    border: 1px solid var(--sunset-orange);
    box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
    color: #fff;
}

.modal-header {
    border-bottom: 1px solid rgba(255, 123, 37, 0.2);
    padding: 1.5rem;
}

.modal-header .modal-title {
    color: #fff;
    font-size: 1.5rem;
    font-weight: 600;
}

.modal-header .btn-close {
    background: none;
    color: #fff;
    opacity: 0.8;
    transition: all 0.3s;
}

.modal-header .btn-close:hover {
    opacity: 1;
    transform: rotate(90deg);
}

.modal-body {
    padding: 1.5rem;
}

.modal-body .form-label {
    color: #fff;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.modal-body .form-control {
    background-color: rgba(26, 26, 46, 0.8);
    border: 1px solid rgba(255, 123, 37, 0.3);
    color: #fff;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    transition: all 0.3s;
}

.modal-body .form-control::placeholder {
    color: rgba(255, 255, 255, 0.5);
}

.modal-body .form-control:focus {
    background-color: rgba(26, 26, 46, 0.9);
    border-color: var(--sunset-orange);
    box-shadow: 0 0 0 2px rgba(255, 123, 37, 0.2);
    outline: none;
    color: #fff;
}

.modal-body .form-select {
    background-color: rgba(26, 26, 46, 0.8);
    border: 1px solid rgba(255, 123, 37, 0.3);
    color: #fff;
    padding: 0.75rem 1rem;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
}

.modal-body .form-select:focus {
    background-color: rgba(26, 26, 46, 0.9);
    border-color: var(--sunset-orange);
    box-shadow: 0 0 0 2px rgba(255, 123, 37, 0.2);
    outline: none;
}

.modal-body .form-select option {
    background-color: var(--sunset-darker);
    color: #fff;
}

.modal-body .form-check-input {
    background-color: rgba(26, 26, 46, 0.8);
    border: 1px solid rgba(255, 123, 37, 0.3);
    cursor: pointer;
}

.modal-body .form-check-input:checked {
    background-color: var(--sunset-orange);
    border-color: var(--sunset-orange);
}

.modal-body .form-check-label {
    color: #fff;
    cursor: pointer;
}

.modal-footer {
    border-top: 1px solid rgba(255, 123, 37, 0.2);
    padding: 1.5rem;
}

.modal-footer .btn {
    padding: 0.75rem 1.5rem;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s;
}

.modal-footer .btn-secondary {
    background-color: rgba(26, 26, 46, 0.8);
    border: 1px solid rgba(255, 123, 37, 0.3);
    color: #fff;
}

.modal-footer .btn-secondary:hover {
    background-color: rgba(26, 26, 46, 0.9);
    border-color: var(--sunset-orange);
}

.modal-footer .btn-sunset {
    background: linear-gradient(to right, var(--sunset-orange), var(--sunset-red));
    border: none;
    color: #fff;
}

.modal-footer .btn-sunset:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(255, 123, 37, 0.4);
}

/* Password field styles */
.password-field {
    position: relative;
}

.password-field .form-control {
    padding-right: 40px;
}

.password-toggle-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    color: #fff;
    cursor: pointer;
    opacity: 0.8;
    transition: all 0.3s;
}

.password-toggle-icon:hover {
    opacity: 1;
}

/* Form group spacing */
.mb-3 {
    margin-bottom: 1.5rem;
}

/* Alert messages */
.alert {
    border-radius: 6px;
    padding: 1rem;
    margin-bottom: 1rem;
}

.alert-success {
    background-color: rgba(40, 167, 69, 0.2);
    border: 1px solid rgba(40, 167, 69, 0.3);
    color: #2ecc71;
}

.alert-danger {
    background-color: rgba(220, 53, 69, 0.2);
    border: 1px solid rgba(220, 53, 69, 0.3);
    color: #e74c3c;
}

.table {
    --bs-table-bg: transparent;
    --bs-table-striped-bg: rgba(26, 26, 46, 0.1);
    --bs-table-hover-bg: rgba(255, 77, 109, 0.15);
    border-color: rgba(255, 123, 37, 0.2) !important;
}

.table td, .table th, .table tr {
    background-color: rgba(22, 33, 62, 0.7) !important;
    color: var(--text-light) !important;
    border-color: rgba(255, 123, 37, 0.15) !important;
}

.table-hover tbody tr:hover td {
    background-color: rgba(40, 40, 72, 0.9) !important;
    box-shadow: 0 2px 8px rgba(255, 77, 109, 0.2);
}

.table thead th {
    background: linear-gradient(135deg, 
        rgba(255, 123, 37, 0.25), 
        rgba(255, 77, 109, 0.25)) !important;
    color: var(--sunset-orange) !important;
    border-bottom: 2px solid var(--sunset-pink) !important;
}
</style>

<?php require_once 'includes/admin_footer.php'; ?>