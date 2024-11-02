<?php
require_once '../includes/config.php';
require_once '../includes/db.php';
require_once '../includes/functions.php';

// Add new columns if they don't exist
try {
    $pdo->exec("ALTER TABLE profiles ADD COLUMN IF NOT EXISTS firstname VARCHAR(100)");
    $pdo->exec("ALTER TABLE profiles ADD COLUMN IF NOT EXISTS surname VARCHAR(100)");
    $pdo->exec("ALTER TABLE profiles ADD COLUMN IF NOT EXISTS other_names VARCHAR(100)");
    $pdo->exec("ALTER TABLE profiles ADD COLUMN IF NOT EXISTS gender VARCHAR(20)");
    $pdo->exec("ALTER TABLE profiles ADD COLUMN IF NOT EXISTS highest_qualification VARCHAR(100)");
    $pdo->exec("ALTER TABLE profiles ADD COLUMN IF NOT EXISTS state VARCHAR(100)");
    $pdo->exec("ALTER TABLE profiles ADD COLUMN IF NOT EXISTS lga VARCHAR(100)");
    $pdo->exec("ALTER TABLE profiles ADD COLUMN IF NOT EXISTS street_address TEXT");
    $pdo->exec("ALTER TABLE profiles ADD COLUMN IF NOT EXISTS updated TINYINT(1) DEFAULT 0");
} catch (PDOException $e) {
    if ($e->getCode() != '42S21') {
        throw $e;
    }
}

// Start session if not already started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch user profile information
$stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
$stmt->execute([$user_id]);
$profile = $stmt->fetch(PDO::FETCH_ASSOC);

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $firstname = trim($_POST['firstname']);
    $surname = trim($_POST['surname']);
    $other_names = trim($_POST['other_names']);
    $date_of_birth = $_POST['date_of_birth'];
    $occupation = trim($_POST['occupation']);
    $highest_qualification = trim($_POST['highest_qualification']);
    $gender = $_POST['gender'];
    $state = $_POST['state'];
    $lga = $_POST['lga'];
    $street_address = trim($_POST['street_address']);
    $profile_picture = $_FILES['profile_picture'];

    if (empty($firstname) || empty($surname)) {
        $error = "First name and surname are required.";
    } else {
        // Handle profile picture upload
        if ($profile_picture['error'] == UPLOAD_ERR_OK) {
            $upload_dir = '../uploads/';
            $upload_file = $upload_dir . basename($profile_picture['name']);
            if (move_uploaded_file($profile_picture['tmp_name'], $upload_file)) {
                $profile_picture_path = $upload_file;
            } else {
                $error = "Failed to upload profile picture.";
            }
        } else {
            $profile_picture_path = $profile['profile_picture'] ?? null;
        }

        if ($profile) {
            // Update existing profile
            $stmt = $pdo->prepare("UPDATE profiles SET firstname = ?, surname = ?, other_names = ?, 
                date_of_birth = ?, occupation = ?, highest_qualification = ?, gender = ?, 
                state = ?, lga = ?, street_address = ?, profile_picture = ?, updated = 1 
                WHERE user_id = ?");
            $params = [$firstname, $surname, $other_names, $date_of_birth, $occupation, 
                      $highest_qualification, $gender, $state, $lga, $street_address, 
                      $profile_picture_path, $user_id];
        } else {
            // Insert new profile
            $stmt = $pdo->prepare("INSERT INTO profiles (firstname, surname, other_names, 
                date_of_birth, occupation, highest_qualification, gender, state, lga, 
                street_address, profile_picture, user_id, updated) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1)");
            $params = [$firstname, $surname, $other_names, $date_of_birth, $occupation, 
                      $highest_qualification, $gender, $state, $lga, $street_address, 
                      $profile_picture_path, $user_id];
        }

        if ($stmt->execute($params)) {
            $success = "Profile updated successfully.";
            // Refresh profile data
            $stmt = $pdo->prepare("SELECT * FROM profiles WHERE user_id = ?");
            $stmt->execute([$user_id]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            $error = "Failed to update profile. Please try again.";
        }
    }
}

// Determine if the fields should be read-only
$readonly = $profile && $profile['updated'] == 1;

// Nigerian States and LGAs array
$states_lgas = [
    'Abia' => ['Aba North', 'Aba South', 'Arochukwu', 'Bende', 'Ikwuano', 'Isiala Ngwa North', 'Isiala Ngwa South', 'Isuikwuato', 'Obi Ngwa', 'Ohafia', 'Osisioma', 'Ugwunagbo', 'Ukwa East', 'Ukwa West', 'Umuahia North', 'Umuahia South', 'Umu Nneochi'],
    'Adamawa' => ['Demsa', 'Fufore', 'Ganye', 'Girei', 'Gombi', 'Guyuk', 'Hong', 'Jada', 'Lamurde', 'Madagali', 'Maiha', 'Mayo Belwa', 'Michika', 'Mubi North', 'Mubi South', 'Numan', 'Shelleng', 'Song', 'Toungo', 'Yola North', 'Yola South'],
    // Add other states and their LGAs...
];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>JCDA - Profile</title>
    <link rel="icon" href="public/JCDA White.png" type="image/png">
    <link href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css" rel="stylesheet">
    <!-- Keep your existing CSS styles here -->
    <style>
        /* Your existing styles remain the same */
    </style>
</head>
<body>
    <div class="dashboard">
        <!-- Sidebar remains the same -->
        <div class="main-content" id="mainContent">
            <div class="header">
                <button class="btn btn-primary" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                <h1>Welcome, <?php echo htmlspecialchars($username); ?>!</h1>
                <div class="user-profile">
                    <img src="<?php echo $profile ? htmlspecialchars($profile['profile_picture']) : '../assets/images/useravatar.jpg'; ?>" alt="User profile">
                </div>
            </div>
            <section class="profile-summary">
                <h2>Your Profile</h2>
                <?php if (isset($error)): ?>
                    <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>
                <?php if (isset($success)): ?>
                    <div class="alert alert-success"><?php echo $success; ?></div>
                <?php endif; ?>
                <form action="profile.php" method="POST" enctype="multipart/form-data">
                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="surname">Surname:</label>
                                <input type="text" id="surname" name="surname" class="form-control" 
                                    value="<?php echo $profile ? htmlspecialchars($profile['surname']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="firstname">First Name:</label>
                                <input type="text" id="firstname" name="firstname" class="form-control" 
                                    value="<?php echo $profile ? htmlspecialchars($profile['firstname']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="other_names">Other Names:</label>
                                <input type="text" id="other_names" name="other_names" class="form-control" 
                                    value="<?php echo $profile ? htmlspecialchars($profile['other_names']) : ''; ?>">
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="date_of_birth">Date of Birth:</label>
                                <input type="date" id="date_of_birth" name="date_of_birth" class="form-control" 
                                    value="<?php echo $profile ? $profile['date_of_birth'] : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="gender">Gender:</label>
                                <select id="gender" name="gender" class="form-control" required>
                                    <option value="">Select Gender</option>
                                    <option value="male" <?php echo ($profile && $profile['gender'] == 'male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="female" <?php echo ($profile && $profile['gender'] == 'female') ? 'selected' : ''; ?>>Female</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="occupation">Occupation:</label>
                                <input type="text" id="occupation" name="occupation" class="form-control" 
                                    value="<?php echo $profile ? htmlspecialchars($profile['occupation']) : ''; ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="form-group">
                                <label for="highest_qualification">Highest Academic Qualification:</label>
                                <select id="highest_qualification" name="highest_qualification" class="form-control" required>
                                    <option value="">Select Qualification</option>
                                    <option value="SSCE" <?php echo ($profile && $profile['highest_qualification'] == 'SSCE') ? 'selected' : ''; ?>>SSCE</option>
                                    <option value="ND" <?php echo ($profile && $profile['highest_qualification'] == 'ND') ? 'selected' : ''; ?>>ND</option>
                                    <option value="HND" <?php echo ($profile && $profile['highest_qualification'] == 'HND') ? 'selected' : ''; ?>>HND</option>
                                    <option value="BSc" <?php echo ($profile && $profile['highest_qualification'] == 'BSc') ? 'selected' : ''; ?>>BSc</option>
                                    <option value="MSc" <?php echo ($profile && $profile['highest_qualification'] == 'MSc') ? 'selected' : ''; ?>>MSc</option>
                                    <option value="PhD" <?php echo ($profile && $profile['highest_qualification'] == 'PhD') ? 'selected' : ''; ?>>PhD</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="state">State:</label>
                                <select id="state" name="state" class="form-control" required>
                                    <option value="">Select State</option>
                                    <?php foreach ($states_lgas as $state => $lgas): ?>
                                        <option value="<?php echo htmlspecialchars($state); ?>" 
                                            <?php echo ($profile && $profile['state'] == $state) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($state); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="lga">LGA:</label>
                                <select id="lga" name="lga" class="form-control" required>
                                    <option value="">Select LGA</option>
                                    <?php
                                    if ($profile && $profile['state']) {
                                        foreach ($states_lgas[$profile['state']] as $lga) {
                                            echo '<option value="' . htmlspecialchars($lga) . '" ' . 
                                                ($profile['lga'] == $lga ? 'selected' : '') . '>' . 
                                                htmlspecialchars($lga) . '</option>';
                                        }
                                    }
                                    ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-group">
                                <label for="street_address">Street Address:</label>
                                <input type="text" id="street_address" name="street_address" class="form-control" 
                                    value="<?php echo $profile ? htmlspecialchars($profile['street_address']) : ''; ?>" required>
                            </div>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="profile_picture">Profile Picture:</label>
                        <input type="file" id="profile_picture" name="profile_picture" class="form-control-file">
                    </div>

                    <button type="submit" class="btn btn-primary">Update Profile</button>
                </form>
                <a href="dashboard.php" class="btn btn-secondary mt-3">Back to Dashboard</a>
            </section>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.5.1.slim.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.9.2/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    
    <script>
        // Function to show tooltips
        function showTooltip(element, message) {
            const tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.innerText = message;
            document.body.appendChild(tooltip);
            const rect = element.getBoundingClientRect();
            tooltip.style.left = `${rect.left + window.scrollX + element.offsetWidth / 2 - tooltip.offsetWidth / 2}px`;
            tooltip.style.top = `${rect.top + window.scrollY - tooltip.offsetHeight - 5}px`;
            element.addEventListener('mouseleave', () => {
                tooltip.remove();
            });
        }

        // Nigerian States and LGAs data
        const statesLGAs = {
            'Abia': ['Aba North', 'Aba South', 'Arochukwu', 'Bende', 'Ikwuano', 'Isiala Ngwa North', 'Isiala Ngwa South', 'Isuikwuato', 'Obi Ngwa', 'Ohafia', 'Osisioma', 'Ugwunagbo', 'Ukwa East', 'Ukwa West', 'Umuahia North', 'Umuahia South', 'Umu Nneochi'],
            'Adamawa': ['Demsa', 'Fufore', 'Ganye', 'Girei', 'Gombi', 'Guyuk', 'Hong', 'Jada', 'Lamurde', 'Madagali', 'Maiha', 'Mayo Belwa', 'Michika', 'Mubi North', 'Mubi South', 'Numan', 'Shelleng', 'Song', 'Toungo', 'Yola North', 'Yola South'],
            'Akwa Ibom': ['Abak', 'Eastern Obolo', 'Eket', 'Esit Eket', 'Essien Udim', 'Etim Ekpo', 'Etinan', 'Ibeno', 'Ibesikpo Asutan', 'Ibiono Ibom', 'Ika', 'Ikono', 'Ikot Abasi', 'Ikot Ekpene', 'Ini', 'Itu', 'Mbo', 'Mkpat Enin', 'Nsit Atai', 'Nsit Ibom', 'Nsit Ubium', 'Obot Akara', 'Okobo', 'Onna', 'Oron', 'Oruk Anam', 'Udung Uko', 'Ukanafun', 'Uruan', 'Urue Offong Oruko', 'Uyo'],
            'Anambra': ['Aguata', 'Anambra East', 'Anambra West', 'Anaocha', 'Awka North', 'Awka South', 'Ayamelum', 'Dunukofia', 'Ekwusigo', 'Idemili North', 'Idemili South', 'Ihiala', 'Njikoka', 'Nnewi North', 'Nnewi South', 'Ogbaru', 'Onitsha North', 'Onitsha South', 'Orumba North', 'Orumba South', 'Oyi'],
            'Bauchi': ['Alkaleri', 'Bauchi', 'Bogoro', 'Damban', 'Darazo', 'Dass', 'Gamawa', 'Ganjuwa', 'Giade', 'Itas/Gadau', 'Jama\'are', 'Katagum', 'Kirfi', 'Misau', 'Ningi', 'Shira', 'Tafawa Balewa', 'Toro', 'Warji', 'Zaki'],
            'Bayelsa': ['Brass', 'Ekeremor', 'Kolokuma/Opokuma', 'Nembe', 'Ogbia', 'Sagbama', 'Southern Ijaw', 'Yenagoa'],
            'Benue': ['Ado', 'Agatu', 'Apa', 'Buruku', 'Gboko', 'Guma', 'Gwer East', 'Gwer West', 'Katsina-Ala', 'Konshisha', 'Kwande', 'Logo', 'Makurdi', 'Obi', 'Ogbadibo', 'Ohimini', 'Oju', 'Okpokwu', 'Otukpo', 'Tarka', 'Ukum', 'Ushongo', 'Vandeikya'],
            // Add all other states and their LGAs here
        };

        document.addEventListener('DOMContentLoaded', () => {
            // Toggle sidebar functionality
            document.getElementById('toggleSidebar').addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('hidden');
                document.getElementById('sidebar').classList.toggle('expanded');
                document.getElementById('mainContent').classList.toggle('expanded');
            });

            // Sidebar tooltips
            document.querySelectorAll('.sidebar a').forEach(link => {
                link.addEventListener('mouseenter', () => {
                    if (!document.querySelector('.sidebar').classList.contains('expanded')) {
                        showTooltip(link, link.querySelector('.sidebar-text').innerText);
                    }
                });
            });

            // Handle State and LGA dropdowns
            const stateSelect = document.getElementById('state');
            const lgaSelect = document.getElementById('lga');

            // Function to update LGA options
            function updateLGAOptions() {
                const selectedState = stateSelect.value;
                lgaSelect.innerHTML = '<option value="">Select LGA</option>';
                
                if (selectedState && statesLGAs[selectedState]) {
                    statesLGAs[selectedState].forEach(lga => {
                        const option = document.createElement('option');
                        option.value = lga;
                        option.textContent = lga;
                        lgaSelect.appendChild(option);
                    });
                }
            }

            // Add event listener to state select
            stateSelect.addEventListener('change', updateLGAOptions);

            // Form validation
            const form = document.querySelector('form');
            form.addEventListener('submit', function(event) {
                const requiredFields = form.querySelectorAll('[required]');
                let isValid = true;

                requiredFields.forEach(field => {
                    if (!field.value.trim()) {
                        isValid = false;
                        field.classList.add('is-invalid');
                    } else {
                        field.classList.remove('is-invalid');
                    }
                });

                if (!isValid) {
                    event.preventDefault();
                    alert('Please fill in all required fields.');
                }
            });

            // Profile picture preview
            const profilePictureInput = document.getElementById('profile_picture');
            profilePictureInput.addEventListener('change', function(event) {
                const file = event.target.files[0];
                if (file) {
                    const reader = new FileReader();
                    reader.onload = function(e) {
                        const profileImage = document.querySelector('.user-profile img');
                        profileImage.src = e.target.result;
                    };
                    reader.readAsDataURL(file);
                }
            });

            // Date of birth validation
            const dobInput = document.getElementById('date_of_birth');
            dobInput.addEventListener('change', function() {
                const selectedDate = new Date(this.value);
                const today = new Date();
                const minAge = 18;
                const maxAge = 100;

                const age = today.getFullYear() - selectedDate.getFullYear();
                
                if (age < minAge || age > maxAge) {
                    alert('Please enter a valid date of birth. Age must be between 18 and 100 years.');
                    this.value = '';
                }
            });

            // Initialize any Bootstrap components
            $('[data-toggle="tooltip"]').tooltip();
            $('[data-toggle="popover"]').popover();
        });

        // Function to handle profile picture preview
        function previewProfilePicture(input) {
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.user-profile img').src = e.target.result;
                };
                reader.readAsDataURL(input.files[0]);
            }
        }
    </script>

    <script>
        // Add custom form validation styles
        document.querySelectorAll('.form-control').forEach(input => {
            input.addEventListener('invalid', function(e) {
                e.preventDefault();
                this.classList.add('is-invalid');
            });

            input.addEventListener('input', function() {
                if (this.checkValidity()) {
                    this.classList.remove('is-invalid');
                }
            });
        });
    </script>
</body>
</html>