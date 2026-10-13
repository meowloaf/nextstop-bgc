const profileDialog = document.getElementById("profile-dialog");
const profileTrigger = document.getElementById("profile-trigger");
const profileClose = document.getElementById("profile-close");
const profileDetails = document.getElementById("profile-details");
const profileForm = document.getElementById("profile-form");
const profileEdit = document.getElementById("profile-edit");
const profileCancel = document.getElementById("profile-cancel");

function closeProfileDialog() {
    profileDialog.hidden = true;
    profileTrigger.focus();
}

function showProfileEditor() {
    profileDetails.hidden = true;
    profileForm.hidden = false;
    document.getElementById("profile-name").focus();
}

function showProfileDetails() {
    profileForm.reset();
    profileForm.hidden = true;
    profileDetails.hidden = false;
    profileEdit.focus();
}

profileTrigger.addEventListener("click", () => {
    profileDialog.hidden = false;
    if (profileForm.hidden) {
        profileEdit.focus();
    } else {
        document.getElementById("profile-name").focus();
    }
});

profileEdit.addEventListener("click", showProfileEditor);
profileCancel.addEventListener("click", showProfileDetails);
profileClose.addEventListener("click", closeProfileDialog);
profileDialog.addEventListener("click", event => {
    if (event.target === profileDialog) {
        closeProfileDialog();
    }
});
document.addEventListener("keydown", event => {
    if (event.key === "Escape" && !profileDialog.hidden) {
        closeProfileDialog();
    }
});

if (!profileDialog.hidden) {
    if (profileForm.hidden) {
        profileEdit.focus();
    } else {
        document.getElementById("profile-name").focus();
    }
}
