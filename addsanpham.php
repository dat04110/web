<?php
// Log configuration
$logFile = 'debug.log';
file_put_contents($logFile, date('Y-m-d H:i:s') . " - Starting addsanpham.php processing\n", FILE_APPEND);

// Database connection configuration
$host = 'localhost';
$dbname = 'se07201_sdlc';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully connected to MySQL with $dbname\n", FILE_APPEND);
} catch (PDOException $e) {
    try {
        $pdo = new PDO("mysql:host=$host;charset=utf8", $username, $password);
        $pdo->exec("CREATE DATABASE IF NOT EXISTS $dbname");
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Successfully created and reconnected to $dbname\n", FILE_APPEND);
    } catch (PDOException $e) {
        file_put_contents($logFile, date('Y-m-d H:i:s') . " - Database connection/creation error: " . $e->getMessage() . "\n", FILE_APPEND);
        die(json_encode(['success' => false, 'message' => 'Connection error: ' . $e->getMessage()]));
    }
}

// Check and create products table
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS products (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(255) NOT NULL,
            price DECIMAL(10,2) NOT NULL,
            image VARCHAR(500) NOT NULL,
            description TEXT
        ) ENGINE=InnoDB
    ");
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Products table checked/created\n", FILE_APPEND);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Table creation error: " . $e->getMessage() . "\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Table creation error: ' . $e->getMessage()]));
}

// Create uploads directory if it doesn't exist
$uploadDir = 'uploads/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

// Handle AJAX request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $action = $_POST['action'];
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Received request: $action\n", FILE_APPEND);

    if ($action === 'addOrUpdate') {
        $name = trim($_POST['name'] ?? '');
        $price = floatval($_POST['price'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        $id = isset($_POST['id']) ? intval($_POST['id']) : null;

        if (empty($name) || $price <= 0) {
            echo json_encode(['success' => false, 'message' => 'Please enter complete and valid information (name, price)!']);
            exit;
        }

        $imagePath = '';
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $fileTmpPath = $_FILES['image']['tmp_name'];
            $fileName = $_FILES['image']['name'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif'];

            if (!in_array($fileExtension, $allowedExtensions)) {
                echo json_encode(['success' => false, 'message' => 'Only JPG, JPEG, PNG, or GIF files are supported!']);
                exit;
            }

            $newFileName = uniqid('img_') . '.' . $fileExtension;
            $destPath = $uploadDir . $newFileName;

            if (!move_uploaded_file($fileTmpPath, $destPath)) {
                echo json_encode(['success' => false, 'message' => 'Error uploading image!']);
                exit;
            }
            $imagePath = $destPath;
        } elseif ($id) {
            // If updating without uploading a new image, keep the old image
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $imagePath = $stmt->fetchColumn();
        } else {
            echo json_encode(['success' => false, 'message' => 'Please select an image!']);
            exit;
        }

        try {
            if ($id) {
                $stmt = $pdo->prepare("UPDATE products SET name = ?, price = ?, image = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $price, $imagePath, $description, $id]);
                echo json_encode(['success' => true, 'message' => 'Update successful!', 'id' => $id, 'name' => $name, 'price' => $price, 'image' => $imagePath, 'description' => $description]);
            } else {
                $stmt = $pdo->prepare("INSERT INTO products (name, price, image, description) VALUES (?, ?, ?, ?)");
                $stmt->execute([$name, $price, $imagePath, $description]);
                $lastId = $pdo->lastInsertId();
                echo json_encode(['success' => true, 'message' => 'Added successfully!', 'id' => $lastId, 'name' => $name, 'price' => $price, 'image' => $imagePath, 'description' => $description]);
            }
        } catch (PDOException $e) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - SQL error: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }

    if ($action === 'delete') {
        $id = intval($_POST['id'] ?? 0);
        if ($id <= 0) {
            echo json_encode(['success' => false, 'message' => 'Invalid product ID!']);
            exit;
        }

        try {
            // Delete related image file
            $stmt = $pdo->prepare("SELECT image FROM products WHERE id = ?");
            $stmt->execute([$id]);
            $imagePath = $stmt->fetchColumn();
            if ($imagePath && file_exists($imagePath)) {
                unlink($imagePath);
            }

            $stmt = $pdo->prepare("DELETE FROM products WHERE id = ?");
            $stmt->execute([$id]);
            echo json_encode(['success' => true, 'message' => 'Deleted successfully!']);
        } catch (PDOException $e) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . " - Delete error: " . $e->getMessage() . "\n", FILE_APPEND);
            echo json_encode(['success' => false, 'message' => 'Database error: ' . $e->getMessage()]);
        }
        exit;
    }
}

// Fetch product list
try {
    $stmt = $pdo->query("SELECT * FROM products");
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    file_put_contents($logFile, date('Y-m-d H:i:s') . " - Error fetching product list: " . $e->getMessage() . "\n", FILE_APPEND);
    die(json_encode(['success' => false, 'message' => 'Error fetching product list: ' . $e->getMessage()]));
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Coffee Shop Product Management</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; background-color: #f8f1e9; }
        h1, h2 { text-align: center; color: #3c2f2f; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; background-color: white; border-radius: 8px; box-shadow: 0 0 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 15px; }
        .form-group label { display: block; margin-bottom: 5px; font-weight: bold; }
        .form-group input, .form-group textarea { width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 4px; }
        button { padding: 10px 15px; background-color: #6f4e37; color: white; border: none; border-radius: 4px; cursor: pointer; margin-right: 10px; }
        button:hover { background-color: #5a3c2a; }
        .preview { margin-top: 20px; padding: 10px; border: 1px solid #ddd; border-radius: 4px; display: none; }
        .preview img { width: 100px; height: 100px; object-fit: cover; margin-bottom: 10px; }
        .preview button { background-color: #6f4e37; margin-right: 10px; }
        .preview button[onclick*="update"] { background-color: #2196F3; }
        .preview button[onclick*="update"]:hover { background-color: #1976D2; }
        .product-list { margin-top: 30px; }
        .product-item { display: flex; align-items: center; padding: 10px; border-bottom: 1px solid #ddd; }
        .product-item img { width: 60px; height: 60px; object-fit: cover; margin-right: 15px; border-radius: 4px; }
        .product-item .info { flex-grow: 1; }
        .product-item .description { font-size: 0.9em; color: #666; margin-top: 5px; }
        .product-item button { margin-left: 10px; background-color: #f44336; }
        .product-item button.edit { background-color: #2196F3; }
        .product-item button:hover { opacity: 0.9; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Coffee Shop Product Management</h1>
        <form id="productForm" enctype="multipart/form-data">
            <div class="form-group">
                <label for="productName">Product Name</label>
                <input type="text" id="productName" name="name" placeholder="Enter product name">
            </div>
            <div class="form-group">
                <label for="productPrice">Price (VND)</label>
                <input type="number" id="productPrice" name="price" placeholder="Enter price" min="0" step="1000">
            </div>
            <div class="form-group">
                <label for="productImage">Image</label>
                <input type="file" id="productImage" name="image" accept="image/*">
            </div>
            <div class="form-group">
                <label for="productDescription">Description</label>
                <textarea id="productDescription" name="description" placeholder="Enter product description" rows="4"></textarea>
            </div>
            <button type="button" onclick="previewProduct()">Preview</button>
        </form>
        <div class="preview" id="previewSection">
            <h3>Product Preview</h3>
            <img id="previewImage" src="" alt="Product image">
            <p><strong>Name:</strong> <span id="previewName"></span></p>
            <p><strong>Price:</strong> <span id="previewPrice"></span> VND</p>
            <p><strong>Description:</strong> <span id="previewDescription"></span></p>
            <button onclick="addOrUpdateProduct('confirm')">Confirm</button>
            <button onclick="addOrUpdateProduct('update')" id="updateButton" style="display: none;">Update</button>
        </div>
        <h2>Product List</h2>
        <div class="product-list" id="productList">
            <?php if (!empty($products)): ?>
                <?php foreach ($products as $product): ?>
                    <div class="product-item" data-id="<?php echo $product['id']; ?>">
                        <img src="<?php echo htmlspecialchars($product['image'], ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?>">
                        <div class="info">
                            <strong><?php echo htmlspecialchars($product['name'], ENT_QUOTES, 'UTF-8'); ?></strong> - <?php echo number_format($product['price'], 0, ',', '.'); ?> VND
                            <div class="description"><?php echo htmlspecialchars($product['description'] ?? 'No description', ENT_QUOTES, 'UTF-8'); ?></div>
                        </div>
                        <button class="edit" onclick="editProduct(<?php echo $product['id']; ?>, '<?php echo htmlspecialchars(addslashes($product['name']), ENT_QUOTES, 'UTF-8'); ?>', <?php echo $product['price']; ?>, '<?php echo htmlspecialchars(addslashes($product['image']), ENT_QUOTES, 'UTF-8'); ?>', '<?php echo htmlspecialchars(addslashes($product['description'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>')">Edit</button>
                        <button onclick="deleteProduct(<?php echo $product['id']; ?>)">Delete</button>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p id="noProducts">No products available.</p>
            <?php endif; ?>
        </div>
    </div>

    <script>
        let editId = null;

        function previewProduct() {
            const name = document.getElementById('productName').value.trim();
            const price = document.getElementById('productPrice').value;
            const fileInput = document.getElementById('productImage');
            const description = document.getElementById('productDescription').value.trim();

            if (!name || !price) {
                alert('Please enter complete information (name, price)!');
                return;
            }

            if (!editId && (!fileInput.files || !fileInput.files[0])) {
                alert('Please select an image!');
                return;
            }

            const previewImage = () => {
                document.getElementById('previewName').textContent = name;
                document.getElementById('previewPrice').textContent = parseFloat(price).toLocaleString('vi-VN');
                document.getElementById('previewDescription').textContent = description || 'No description';
                document.getElementById('previewSection').style.display = 'block';
                document.getElementById('updateButton').style.display = editId ? 'inline-block' : 'none';
            };

            if (fileInput.files && fileInput.files[0]) {
                const reader = new FileReader();
                reader.onload = (e) => {
                    document.getElementById('previewImage').src = e.target.result;
                    previewImage();
                };
                reader.onerror = () => alert('Error reading image file!');
                reader.readAsDataURL(fileInput.files[0]);
            } else if (editId) {
                const existingImage = document.querySelector(`.product-item[data-id="${editId}"] img`).src;
                document.getElementById('previewImage').src = existingImage;
                previewImage();
            }
        }

        function addOrUpdateProduct(action) {
            const form = document.getElementById('productForm');
            const name = document.getElementById('productName').value.trim();
            const price = document.getElementById('productPrice').value;
            const fileInput = document.getElementById('productImage');
            const description = document.getElementById('productDescription').value.trim();

            if (!name || !price) {
                alert('Please enter complete information (name, price)!');
                return;
            }

            if (!editId && (!fileInput.files || !fileInput.files[0])) {
                alert('Please select an image!');
                return;
            }

            const formData = new FormData(form);
            formData.append('action', 'addOrUpdate');
            if (action === 'update' && editId) {
                formData.append('id', editId);
            }

            fetch(window.location.href, {
                method: 'POST',
                body: formData
            })
            .then(response => {
                if (!response.ok) throw new Error('Server error: ' + response.status);
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    const productList = document.getElementById('productList');
                    const noProducts = document.getElementById('noProducts');
                    if (action === 'update' && editId) {
                        const productItem = document.querySelector(`.product-item[data-id="${editId}"]`);
                        if (productItem) {
                            productItem.querySelector('img').src = result.image;
                            productItem.querySelector('img').alt = name;
                            productItem.querySelector('.info').innerHTML = `
                                <strong>${name}</strong> - ${parseFloat(price).toLocaleString('vi-VN')} VND
                                <div class="description">${description || 'No description'}</div>
                            `;
                            productItem.querySelector('.edit').setAttribute('onclick', `editProduct(${editId}, '${name.replace(/'/g, "\\'")}', ${price}, '${result.image.replace(/'/g, "\\'")}', '${description.replace(/'/g, "\\'")}')`);
                        }
                    } else {
                        if (noProducts) noProducts.remove();
                        const newProduct = document.createElement('div');
                        newProduct.className = 'product-item';
                        newProduct.setAttribute('data-id', result.id);
                        newProduct.innerHTML = `
                            <img src="${result.image}" alt="${name}">
                            <div class="info">
                                <strong>${name}</strong> - ${parseFloat(price).toLocaleString('vi-VN')} VND
                                <div class="description">${description || 'No description'}</div>
                            </div>
                            <button class="edit" onclick="editProduct(${result.id}, '${name.replace(/'/g, "\\'")}', ${price}, '${result.image.replace(/'/g, "\\'")}', '${description.replace(/'/g, "\\'")}')">Edit</button>
                            <button onclick="deleteProduct(${result.id})">Delete</button>
                        `;
                        productList.appendChild(newProduct);
                    }
                    clearFormAndPreview();
                    alert(result.message);
                    setTimeout(() => window.location.href = 'ASM.php', 1000); // Redirect after 1 second
                } else {
                    alert(result.message);
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
                alert('Error sending request: ' + error.message);
            });
        }

        function editProduct(id, name, price, image, description) {
            editId = id;
            document.getElementById('productName').value = name;
            document.getElementById('productPrice').value = price;
            document.getElementById('productDescription').value = description;
            document.getElementById('productImage').value = ''; // Reset file input
            previewProduct();
        }

        function deleteProduct(id) {
            if (!confirm('Are you sure you want to delete this product?')) return;

            fetch(window.location.href, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: new URLSearchParams({ action: 'delete', id })
            })
            .then(response => {
                if (!response.ok) throw new Error('Server error: ' + response.status);
                return response.json();
            })
            .then(result => {
                if (result.success) {
                    const productItem = document.querySelector(`.product-item[data-id="${id}"]`);
                    if (productItem) productItem.remove();
                    if (!document.querySelector('.product-item')) {
                        const productList = document.getElementById('productList');
                        const noProducts = document.createElement('p');
                        noProducts.id = 'noProducts';
                        noProducts.textContent = 'No products available.';
                        productList.appendChild(noProducts);
                    }
                    alert(result.message);
                    setTimeout(() => window.location.href = 'ASM.php', 5000);
                } else {
                    alert(result.message);
                }
            })
            .catch(error => {
                console.error('AJAX error:', error);
                alert('Error sending request: ' + error.message);
            });
        }

        function clearFormAndPreview() {
            document.getElementById('productName').value = '';
            document.getElementById('productPrice').value = '';
            document.getElementById('productImage').value = '';
            document.getElementById('productDescription').value = '';
            document.getElementById('previewSection').style.display = 'none';
            document.getElementById('updateButton').style.display = 'none';
            editId = null;
        }
    </script>
</body>
</html>