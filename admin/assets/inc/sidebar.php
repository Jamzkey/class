<style>
    /* Sidebar Navigation Styles */
    .left-side-menu {
        width: 250px;
        background: #343a40; /* Dark Gray Sidebar */
        padding: 10px 0;
        font-family: Arial, sans-serif;
    }

    .menu-title {
        font-size: 14px;
        font-weight: bold;
        padding: 10px 20px;
        text-transform: uppercase;
        color: #ced4da; /* Light gray title */
        margin-bottom: 10px;
    }

    .has-submenu > a, .nav-second-level li a {
        display: flex;
        align-items: center;
        padding: 10px 20px;
        text-decoration: none;
        color: #f8f9fa; /* Light gray text */
        font-size: 14px;
        font-weight: 500;
        transition: 0.3s;
    }

    .has-submenu > a:hover, .nav-second-level li a:hover {
        color: #adb5bd; /* Lighter gray */
        background-color: #495057; /* Hover background */
        border-radius: 4px;
    }

    .nav-second-level {
        display: none;
        margin-left: 15px; /* Slight indentation for submenus */
        list-style: none;
        padding-left: 0;
    }

    .has-submenu.active .nav-second-level {
        display: block;
    }

    .nav-second-level li {
        padding: 5px 0;
    }

    .nav-second-level li a {
        font-size: 13px;
        color: #ced4da; /* Light gray text */
        padding: 5px 15px;
    }

    .nav-second-level li a:hover {
        color: #ffffff; /* White for hover */
    }

    /* Align icons and text */
    .has-submenu > a i, .nav-second-level li a i {
        margin-right: 10px;
        font-size: 16px;
        color: #adb5bd;
    }

    /* Menu arrow */
    .menu-arrow {
        margin-left: auto;
        font-size: 12px;
        color: #ced4da;
    }

    /* Active submenu styles */
    .has-submenu.active > a {
        color: #ffffff;
        font-weight: bold;
    }
</style>

<div class="left-side-menu">
    <div class="slimscroll-menu">
        <!--- Sidemenu -->
        <div id="sidebar-menu">
            <ul class="metismenu" id="side-menu">
                <li class="menu-title">Navigation</li>

                <li>
                    <a href="dashboard.php">
                        <i class="fe-airplay"></i>
                        <span> Dashboard </span>
                    </a>
                </li>

                <li class="has-submenu">
                    <a href="javascript:void(0);">
                        <i class="fas fa-chalkboard-teacher"></i>
                        <span> Manage Faculty </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="nav-second-level" aria-expanded="false">
                        <li>
                            <a href="facultyload.php">Faculty Load</a>
                        </li>
                    </ul>
                </li>

                 <li class="has-submenu">
                    <a href="javascript:void(0);">
                        <i class="fas fa-desktop"></i>
                        <span> Manage Lab </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="nav-second-level" aria-expanded="false">
                        <li>
                            <a href="laboratory.php">Laboratory</a>
                        </li>
                    </ul>
                </li>


                 <li class="has-submenu">
                    <a href="javascript:void(0);">
                        <i class="fas fa-puzzle-piece"></i>
                        <span> Manage Subject </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="nav-second-level" aria-expanded="false">
                        <li>
                            <a href="subject_prospectus.php">Subject Prospectus & Offerings</a>
                        </li>
                    </ul>
                </li>

                <li class="has-submenu">
                    <a href="javascript:void(0);">
                       <i class="fas fa-spinner"></i>
                        <span> Manage Loading </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="nav-second-level" aria-expanded="false">
                        <li>
                            <a href="submission_loading.php">Loading Submission</a>
                        </li>
                    </ul>
                </li>

                 <li class="has-submenu">
                    <a href="javascript:void(0);">
                        <i class="fas fa-play-circle"></i>
                        <span> Auto Schedules </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="nav-second-level" aria-expanded="false">
                        <li>
                            <a href="generate_sched.php">Laboratory & Sched Course</a>
                        </li>
                    </ul>
                </li>

                <li class="has-submenu">
                    <a href="javascript:void(0);">
                        <i class="fas fa-user-check"></i>
                        <span> Faculty Approval </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="nav-second-level" aria-expanded="false">
                        <li>
                            <a href="faculty_approval.php">Manage Approval</a>
                        </li>
                    </ul>
                </li>


                <li class="has-submenu">
                    <a href="javascript:void(0);">
                        <i class="fas fa-user"></i>
                        <span> My Profile </span>
                        <span class="menu-arrow"></span>
                    </a>
                    <ul class="nav-second-level" aria-expanded="false">
                        <li>
                            <a href="view_profile.php">View Profile</a>
                        </li>
                        <li>
                            <a href="changepass.php">Change Password</a>
                        </li>
                    </ul>
                </li>
            </ul>
        </div>
        <!-- End Sidebar -->
        <div class="clearfix"></div>
    </div>
</div>

<script>
    $(document).ready(function () {
        $('.has-submenu > a').on('click', function (e) {
            e.preventDefault();
            const parent = $(this).parent();

            // Toggle the active class
            parent.toggleClass('active');

            // Optionally, collapse other open submenus
            $('.has-submenu').not(parent).removeClass('active');
        });
    });
</script>
