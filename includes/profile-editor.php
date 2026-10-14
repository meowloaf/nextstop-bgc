<?php
function handleProfileUpdate(PDO $pdo, int $userId): array
{
    $message = $_SESSION['profile_message'] ?? '';
    unset($_SESSION['profile_message']);
    $error = '';

    $profileStmt = $pdo->prepare(
        'SELECT username, email, fare_type, emergency_contact_name, emergency_contact
         FROM users WHERE id = ?'
    );
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) {
        unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['role']);
        $_SESSION['login_error'] = 'Your account could not be found. Please sign in again.';
        session_regenerate_id(true);
        $scriptPath = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
        $loginPath = str_contains($scriptPath, '/admin/') ? '../index.php' : 'index.php';
        header('Location: ' . $loginPath);
        exit;
    }

    $profileEmail = $profile['email'] ?? '';
    $emergencyContactName = $profile['emergency_contact_name'] ?? '';
    $emergencyContact = $profile['emergency_contact'] ?? '';
    $fareType = $profile['fare_type'] ?: 'regular';
    $profileName = $profile['username'];

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update-profile') {
        $postedName = $_POST['profile_name'] ?? '';
        $postedEmail = $_POST['profile_email'] ?? '';
        $postedContactName = $_POST['emergency_contact_name'] ?? '';
        $postedContact = $_POST['emergency_contact'] ?? '';
        $postedFareType = $_POST['fare_type'] ?? 'regular';
        $submittedName = trim(is_string($postedName) ? $postedName : '');
        $submittedEmail = trim(is_string($postedEmail) ? $postedEmail : '');
        $submittedContactName = trim(is_string($postedContactName) ? $postedContactName : '');
        $submittedContact = trim(is_string($postedContact) ? $postedContact : '');
        $submittedFareType = is_string($postedFareType) ? $postedFareType : '';
        $profileName = $submittedName !== '' ? $submittedName : $profileName;
        $profileEmail = $submittedEmail !== '' ? $submittedEmail : $profileEmail;
        $emergencyContactName = $submittedContactName !== '' ? $submittedContactName : $emergencyContactName;
        $emergencyContact = $submittedContact !== '' ? $submittedContact : $emergencyContact;
        $fareType = $submittedFareType !== '' ? $submittedFareType : $fareType;

        if ($profileName === '' || !preg_match('/^[A-Za-z0-9 _.-]{3,50}$/', $profileName)) {
            $error = 'Use a username between 3 and 50 characters.';
        } elseif ($profileEmail !== '' && (!filter_var($profileEmail, FILTER_VALIDATE_EMAIL) || strlen($profileEmail) > 255)) {
            $error = 'Enter a valid email address.';
        } elseif (!in_array($fareType, ['regular', 'student', 'pwd', 'senior'], true)) {
            $error = 'Choose a valid account type.';
        } elseif ($emergencyContactName !== '' && !preg_match('/^[A-Za-z .\'-]{2,100}$/', $emergencyContactName)) {
            $error = 'Enter a valid emergency contact name.';
        } elseif ($emergencyContact !== '' && !preg_match('/^[0-9+() .-]{7,30}$/', $emergencyContact)) {
            $error = 'Enter a valid emergency contact number.';
        }

        if ($error === '' && $profileEmail !== '') {
            $emailCheck = $pdo->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $emailCheck->execute([$profileEmail, $userId]);
            if ($emailCheck->fetch()) {
                $error = 'That email address is already in use.';
            }
        }

        if ($error === '') {
            try {
                $update = $pdo->prepare(
                    'UPDATE users
                     SET username = ?, email = ?, fare_type = ?, emergency_contact_name = ?, emergency_contact = ?
                     WHERE id = ?'
                );
                $update->execute([
                    $profileName,
                    $profileEmail !== '' ? $profileEmail : null,
                    $fareType,
                    $emergencyContactName ?: null,
                    $emergencyContact ?: null,
                    $userId,
                ]);
                $_SESSION['username'] = $profileName;
                $_SESSION['profile_message'] = 'Profile updated successfully.';
                $profile['username'] = $profileName;
                $profile['email'] = $profileEmail;
                $profile['fare_type'] = $fareType;
                $profile['emergency_contact_name'] = $emergencyContactName;
                $profile['emergency_contact'] = $emergencyContact;

                $currentPage = $_SERVER['SCRIPT_NAME'] ?? '';
                if ($currentPage !== '') {
                    $queryString = http_build_query($_GET);
                    header('Location: ' . $currentPage . ($queryString !== '' ? '?' . $queryString : ''));
                    exit;
                }
                $message = $_SESSION['profile_message'];
                unset($_SESSION['profile_message']);
            } catch (PDOException $exception) {
                error_log('Profile update failed: ' . $exception->getMessage());
                $error = $exception->getCode() === '23000'
                    ? 'That username or email may already be in use.'
                    : 'Your profile could not be updated right now.';
            }
        }
    }

    $formValues = [
        'profile_name' => $profileName,
        'profile_email' => $profileEmail,
        'fare_type' => $fareType,
        'emergency_contact_name' => $emergencyContactName,
        'emergency_contact' => $emergencyContact,
    ];

    return [$profile, $message, $error, $formValues];
}

function renderProfileEditor(array $profile, string $assetPrefix, string $message, string $error, array $formValues): void
{
    $accountTypeLabels = [
        'regular' => 'Regular',
        'student' => 'Student',
        'pwd' => 'PWD',
        'senior' => 'Senior Citizen',
    ];
    $accountType = $profile['fare_type'] ?: 'regular';
    $showEditor = $error !== '';
    ?>
    <button class="admin-profile-trigger" id="profile-trigger" type="button" aria-haspopup="dialog" aria-controls="profile-dialog">
        <span class="admin-profile-name"><?php echo htmlspecialchars($profile['username']); ?></span>
    </button>
    <?php if ($message !== ''): ?><span class="profile-save-message" role="status"><?php echo htmlspecialchars($message); ?></span><?php endif; ?>
    <div class="profile-dialog" id="profile-dialog" role="dialog" aria-modal="true" aria-labelledby="profile-title" <?php echo $error === '' ? 'hidden' : ''; ?>>
        <div class="profile-dialog-panel">
            <button class="profile-close" id="profile-close" type="button" aria-label="Close profile">×</button>
            <h2 id="profile-title">Your Profile</h2>
            <p>View your account details. Only you can edit this profile.</p>
            <?php if ($error !== ''): ?><p class="form-error" role="alert"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
            <div id="profile-details" class="profile-details" <?php echo $showEditor ? 'hidden' : ''; ?>>
                <div><span>Username</span><strong><?php echo htmlspecialchars($profile['username']); ?></strong></div>
                <div><span>Email</span><strong><?php echo htmlspecialchars($profile['email'] ?: 'Not provided'); ?></strong></div>
                <div><span>Account type</span><strong><?php echo htmlspecialchars($accountTypeLabels[$accountType] ?? 'Regular'); ?></strong></div>
                <div><span>Emergency contact name</span><strong><?php echo htmlspecialchars($profile['emergency_contact_name'] ?: 'Not provided'); ?></strong></div>
                <div><span>Emergency contact number</span><strong><?php echo htmlspecialchars($profile['emergency_contact'] ?: 'Not provided'); ?></strong></div>
                <button class="find-button" id="profile-edit" type="button">Edit Profile</button>
            </div>
            <form method="POST" id="profile-form" <?php echo $showEditor ? '' : 'hidden'; ?>>
                <input type="hidden" name="action" value="update-profile">
                <label for="profile-name">Username</label>
                <input id="profile-name" name="profile_name" value="<?php echo htmlspecialchars($formValues['profile_name']); ?>" maxlength="50" required>
                <label for="profile-email">Email</label>
                <input id="profile-email" name="profile_email" type="email" value="<?php echo htmlspecialchars($formValues['profile_email']); ?>" maxlength="255" autocomplete="email">
                <label for="profile-fare-type">Account type</label>
                <select id="profile-fare-type" name="fare_type">
                    <option value="regular" <?php echo $formValues['fare_type'] === 'regular' ? 'selected' : ''; ?>>Regular</option>
                    <option value="student" <?php echo $formValues['fare_type'] === 'student' ? 'selected' : ''; ?>>Student</option>
                    <option value="pwd" <?php echo $formValues['fare_type'] === 'pwd' ? 'selected' : ''; ?>>PWD</option>
                    <option value="senior" <?php echo $formValues['fare_type'] === 'senior' ? 'selected' : ''; ?>>Senior Citizen</option>
                </select>
                <h3 class="profile-section-title">EMERGENCY CONTACT</h3>
                <label for="emergency-contact-name">Contact name</label>
                <input id="emergency-contact-name" name="emergency_contact_name" value="<?php echo htmlspecialchars($formValues['emergency_contact_name']); ?>" maxlength="100" placeholder="Full name">
                <label for="emergency-contact">Contact number</label>
                <input id="emergency-contact" name="emergency_contact" type="tel" value="<?php echo htmlspecialchars($formValues['emergency_contact']); ?>" maxlength="30" placeholder="09XX XXX XXXX">
                <button class="profile-cancel" id="profile-cancel" type="button">Cancel</button>
                <button class="find-button" type="submit">Save Profile</button>
            </form>
        </div>
    </div>
    <script src="<?php echo htmlspecialchars($assetPrefix); ?>assets/js/profile-editor.js"></script>
    <?php
}
