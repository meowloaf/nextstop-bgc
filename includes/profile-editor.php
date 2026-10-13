<?php
function handleProfileUpdate(PDO $pdo, int $userId): array
{
    $message = $_SESSION['profile_message'] ?? '';
    unset($_SESSION['profile_message']);
    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update-profile') {
        $postedName = $_POST['profile_name'] ?? '';
        $postedContactName = $_POST['emergency_contact_name'] ?? '';
        $postedContact = $_POST['emergency_contact'] ?? '';
        $postedFareType = $_POST['fare_type'] ?? '';
        $profileName = trim(is_string($postedName) ? $postedName : '');
        $emergencyContactName = trim(is_string($postedContactName) ? $postedContactName : '');
        $emergencyContact = trim(is_string($postedContact) ? $postedContact : '');
        $fareType = is_string($postedFareType) ? $postedFareType : '';

        if ($profileName === '' || !preg_match('/^[A-Za-z0-9 _.-]{3,50}$/', $profileName)) {
            $error = 'Use a name between 3 and 50 characters.';
        } elseif (!in_array($fareType, ['', 'student', 'pwd', 'senior'], true)) {
            $error = 'Choose a valid account type.';
        } elseif ($emergencyContactName !== '' && !preg_match('/^[A-Za-z .\'-]{2,100}$/', $emergencyContactName)) {
            $error = 'Enter a valid emergency contact name.';
        } elseif ($emergencyContact !== '' && !preg_match('/^[0-9+() .-]{7,30}$/', $emergencyContact)) {
            $error = 'Enter a valid emergency contact number.';
        }

        if ($error === '') {
            try {
                $update = $pdo->prepare(
                    'UPDATE users
                     SET username = ?, fare_type = ?, emergency_contact_name = ?, emergency_contact = ?
                     WHERE id = ?'
                );
                $update->execute([
                    $profileName,
                    $fareType !== '' ? $fareType : null,
                    $emergencyContactName ?: null,
                    $emergencyContact ?: null,
                    $userId,
                ]);
                $_SESSION['username'] = $profileName;
                $_SESSION['profile_message'] = 'Profile updated successfully.';

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
                    ? 'That name may already be in use.'
                    : 'Your profile could not be updated right now.';
            }
        }
    }

    $profileStmt = $pdo->prepare(
        'SELECT username, fare_type, emergency_contact_name, emergency_contact
         FROM users WHERE id = ?'
    );
    $profileStmt->execute([$userId]);
    $profile = $profileStmt->fetch(PDO::FETCH_ASSOC);
    if (!$profile) {
        throw new RuntimeException('The signed-in account could not be found.');
    }

    $postedName = $_POST['profile_name'] ?? '';
    $postedContactName = $_POST['emergency_contact_name'] ?? '';
    $postedContact = $_POST['emergency_contact'] ?? '';
    $postedFareType = $_POST['fare_type'] ?? '';
    $formValues = [
        'profile_name' => is_string($postedName) ? $postedName : $profile['username'],
        'fare_type' => is_string($postedFareType) ? $postedFareType : ($profile['fare_type'] ?? ''),
        'emergency_contact_name' => is_string($postedContactName) ? $postedContactName : ($profile['emergency_contact_name'] ?? ''),
        'emergency_contact' => is_string($postedContact) ? $postedContact : ($profile['emergency_contact'] ?? ''),
    ];
    if ($message !== '') {
        $formValues = [
            'profile_name' => $profile['username'],
            'fare_type' => $profile['fare_type'] ?? '',
            'emergency_contact_name' => $profile['emergency_contact_name'] ?? '',
            'emergency_contact' => $profile['emergency_contact'] ?? '',
        ];
    }

    return [$profile, $message, $error, $formValues];
}

function renderProfileEditor(array $profile, string $assetPrefix, string $message, string $error, array $formValues): void
{
    $accountTypeLabels = [
        '' => 'Not specified',
        'student' => 'Student',
        'pwd' => 'PWD',
        'senior' => 'Senior Citizen',
    ];
    $accountType = $profile['fare_type'] ?? '';
    $showEditor = $error !== '';
    ?>
    <button class="admin-profile-trigger" id="profile-trigger" type="button" aria-haspopup="dialog" aria-controls="profile-dialog">
        <span class="admin-profile-name"><?php echo htmlspecialchars($profile['username']); ?></span>
    </button>
    <div class="profile-dialog" id="profile-dialog" role="dialog" aria-modal="true" aria-labelledby="profile-title" <?php echo $message === '' && $error === '' ? 'hidden' : ''; ?>>
        <div class="profile-dialog-panel">
            <button class="profile-close" id="profile-close" type="button" aria-label="Close profile">×</button>
            <h2 id="profile-title">Your Profile</h2>
            <p>View your account details. Only you can edit this profile.</p>
            <?php if ($message !== ''): ?><p class="form-success" role="status"><?php echo htmlspecialchars($message); ?></p><?php endif; ?>
            <?php if ($error !== ''): ?><p class="form-error" role="alert"><?php echo htmlspecialchars($error); ?></p><?php endif; ?>
            <div id="profile-details" class="profile-details" <?php echo $showEditor ? 'hidden' : ''; ?>>
                <div><span>Name</span><strong><?php echo htmlspecialchars($profile['username']); ?></strong></div>
                <div><span>Account type</span><strong><?php echo htmlspecialchars($accountTypeLabels[$accountType] ?? 'Not specified'); ?></strong></div>
                <div><span>Emergency contact name</span><strong><?php echo htmlspecialchars($profile['emergency_contact_name'] ?: 'Not provided'); ?></strong></div>
                <div><span>Emergency contact number</span><strong><?php echo htmlspecialchars($profile['emergency_contact'] ?: 'Not provided'); ?></strong></div>
                <button class="find-button" id="profile-edit" type="button">Edit Profile</button>
            </div>
            <form method="POST" id="profile-form" <?php echo $showEditor ? '' : 'hidden'; ?>>
                <input type="hidden" name="action" value="update-profile">
                <label for="profile-name">Name</label>
                <input id="profile-name" name="profile_name" value="<?php echo htmlspecialchars($formValues['profile_name']); ?>" maxlength="50" required>
                <label for="profile-fare-type">Account type</label>
                <select id="profile-fare-type" name="fare_type">
                    <option value="" <?php echo $formValues['fare_type'] === '' ? 'selected' : ''; ?>>Not specified</option>
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
