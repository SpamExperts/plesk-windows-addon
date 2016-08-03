<?php

class Installer
{
    /**
     * @var Filesystem_AbstractFilesystem
     */
    protected $filesystem;

    /**
     * @var Installer_InstallPaths
     */
    protected $paths;

    /**
     * @var SpamFilter_PanelSupport_Plesk
     */
    protected $panelSupport;

    /**
     * @var SpamFilter_Logger
     */
    protected $logger;

    /**
     * @var string
     */
    protected $currentVersion;

    /**
     * @var Output_OutputInterface
     */
    protected $output;

    public function __construct(Installer_InstallPaths $paths, Filesystem_AbstractFilesystem $filesystem, Output_OutputInterface $output)
    {
        $this->output = $output;
        $this->filesystem = $filesystem;
        $this->paths = $paths;
        $this->logger = Zend_Registry::get('logger');
        $this->findCurrentVersionAndInitPanelSupport();
    }

    public function install()
    {
        try {
            $this->doInstall();
        } catch (ShouldRetryException $e) {
            $this->output->error($e->getMessage());
            $this->output->warn("The installation process failed. Running the installer again should fix the problem.");
            $this->logger->debug($e->getMessage());
            $this->logger->debug($e->getTraceAsString());
        } catch (Exception $exception) {
            $this->output->error($exception->getMessage());
            $this->logger->debug($exception->getMessage());
            $this->logger->debug($exception->getTraceAsString());
        }
    }

    private function doInstall()
    {
        $this->outputInstallStartMessage();

        $this->checkRequirementsAreMet();

        if (!$this->panelSupport->minVerCheck()) {
            $this->output->error("The currently used version of your controlpanel doesn't match minimum requirement.");
            exit(1);
        }

        $this->copyFilesToDestination();

        $this->setupConfigDirectory();

        $this->symlinkImagesToWebDir();
        $this->symlinkFrontendToWebDir();
        $this->symlinkImagesToPSFWebDir();
        $this->setupHooks();

        $this->setupBrand();

        $pass = $this->panelSupport->getAdminPassword();
        $this->installCustomButtons($pass, $this->panelSupport->getMySQLPort());

        $this->installUpdateCronjob();
        $this->changeAddonConfigFileOwnership();
        $this->removeSrcFolder();
        $this->removeInstallFileAndDisplaySuccessMessage();
    }

    private function changeAddonConfigFileOwnership()
    {
        $this->output->info("Changing ownership to 'psaadm' for addon config files");

        $this->output->info("Creating security config file in the Disk Security path");
        copy(DEST_PATH . DS . 'bin' . DS . 'prospamfilter.xml', $this->paths->plesk . 'etc' . DS . 'DiskSecurity' . DS . 'prospamfilter.xml' );
        $this->output->info("Done");
        $this->output->info("Applying changes for configuration directory");
        // We need to break inheritance first
        $programFilesDir = getenv('ProgramFiles');
        $programDataDir = getenv('ProgramData');

        exec('icacls "' . $programFilesDir . DS . 'SpamExperts" /inheritance:d /T');
        exec('icacls "' . $programFilesDir . DS . 'SpamExperts"  /remove:d psaadm /remove:d psacln /T' );
        exec('icacls "' . $programDataDir . DS . 'SpamExperts" /inheritance:d /T');
        // sleep for a while
        sleep(5);
        $this->output->info("Applying changes for " . $programDataDir . DS . "SpamExperts");
        exec('"' . $this->paths->plesk . 'admin' . DS . 'bin' . DS . 'ApplySecurity.exe" --apply-to-directory --directory="' . $programDataDir . DS . 'SpamExperts"');
        $this->output->info("Applying changes for " . $programFilesDir . DS . "SpamExperts");
        exec('"' . $this->paths->plesk . 'admin' . DS . 'bin' . DS . 'ApplySecurity.exe" --apply-to-directory --directory="' . $programFilesDir . DS . 'SpamExperts"');
        $this->output->info("Done");
    }

    protected function installUpdateCronjob()
    {
        $hour = mt_rand(20, 23); // Decide on update hour (between 20 and 23)
        $min  = mt_rand(0, 59); // Decide on update minutes (0 to 59)

        $hour = str_pad($hour, 2, 0, STR_PAD_LEFT);
        $min = str_pad($min, 2, 0, STR_PAD_LEFT);

        $this->output->info("Installing cronjob for automatic updates...");
        system('schtasks /create /tn "SpamExperts Check Update Task" /tr "' . $this->paths->plesk . 'admin' . DS . 'bin' . DS . 'php.exe - q ' .
            $this->paths->destination . DS . 'bin' . DS . 'checkUpdate.php" /sc weekly /st ' . $hour . ':' . $min . ' /F');
    }

    private function setupConfigDirectory()
    {
        $this->output->info("Prepopulating config folder");
        $this->createConfigDirectory();
        $this->createCacheFolder();
        touch($this->paths->config . DS . "settings.conf");
        touch($this->paths->config . DS . "branding.conf");
    }

    private function createCacheFolder()
    {
        $cacheFolder = getenv('ProgramData') . DS . 'SpamExperts' . DS . 'tmp' . DS . 'cache';

        if (file_exists($cacheFolder)) {
            return;
        }

        @mkdir($cacheFolder, 0755, true);

        if (!file_exists($cacheFolder)) {
            die("[ERROR] Unable to create cache folder!");
        }
    }

    private function findCurrentVersionAndInitPanelSupport()
    {
        $options = array('skipapi' => true);

        if (file_exists("/usr/local/prospamfilter2/application/version.txt")) {
            $this->logger->debug("[Install] PSF2 version file exists");
            $this->currentVersion = trim(file_get_contents("/usr/local/prospamfilter2/application/version.txt"));
            if (version_compare($this->currentVersion, '3.0.0', '<')) {
                $this->logger->debug("[Install] Version is below 3.0.0");
                if (file_exists('/etc/prospamfilter2/settings.conf')) {
                    $this->logger->debug("[Install] Changing config to old");
                    $options['altconfig'] = "/etc/prospamfilter2/settings.conf";
                }
            }
        } elseif (file_exists($this->paths->destination . DS . 'application' . DS . 'version.txt')) {
            $this->logger->debug("[Install] New version file found post 3.0.0");
            $this->currentVersion = trim(file_get_contents($this->paths->destination . DS . 'application' . DS . 'version.txt'));
        } else {
            $this->logger->debug("[Install] No version file found, must be new install.");
            $this->currentVersion = null; //no version set, must be an upgrade
        }

        $this->panelSupport = new SpamFilter_PanelSupport_Plesk($options);
    }

    private function setupBrand()
    {
        $brand = new SpamFilter_Brand();

        // Setup initial icon, but only if it does not exist already.
        $icon_content = base64_decode($brand->getBrandIcon(true));
        $iconPath = $this->paths->plesk . 'admin' . DS . 'htdocs' . DS . 'images' . DS . 'custom_buttons' . DS . 'prospamfilter.png';

        if (!file_exists($iconPath)) {
            file_put_contents($iconPath, $icon_content);
        }
    }

    private function outputInstallStartMessage()
    {
        $version = $this->getVersion();
        $this->output->info("This system will install Professional Spam Filter v{$version} for Plesk Windows");
    }

    private function checkRequirementsAreMet()
    {
        if (version_compare($this->panelSupport->getVersion(), '12.6', '>=')) {
            $this->output->error('This addon is not compatible with your Plesk version. Please install our Plesk extension instead.');
            exit(1);
        }

        // We *need* shell_exec.
        if (!function_exists('shell_exec')) {
            $this->output->error('shell_exec function is required for the installer to work.');
            exit(1);
        }

        try {
            $whoami = shell_exec('whoami');
        } catch (Exception $e) {
            $this->output->error(
                "Error checking current user (via whoami), do you have the command 'whoami' or did you disallow shell_exec?."
            );
            exit(1);
        }

        // More detailed testing
        $selfcheck = SpamFilter_Core::selfCheck(false, array('skipapi' => true));
        $this->output->info("Running selfcheck...");
        if ($selfcheck['status'] != true) {
            if ($selfcheck['critital'] == true) {
                $this->output->error("Failed\nThere are some issues detected, of whom there are critical ones:");
            } else {
                $this->output->warn("Warning\nThere are some (potential) issues detected:");
            }

            foreach ($selfcheck['reason'] as $issue) {
                echo " * {$issue}\n";
            }

            // Wait a short wile.
            sleep(10);
        } else {
            $this->output->ok("Finished without errors");
        }
    }

    private function copyFilesToDestination()
    {
        $this->output->info("Copying files to destination.");

        // Cleanup destination folder.
        $this->output->info("Cleaning up old folder (" . $this->paths->destination . ")..");
        $this->filesystem->removeDirectory($this->paths->destination);
        $this->output->ok("Done");

        $this->output->info("Remove old symlinks");
        $this->filesystem->removeDirectory($this->paths->plesk . 'admin' . DS . 'htdocs' . DS . 'modules' . DS . 'prospamfilter');
        unlink($this->paths->plesk . 'admin' . DS . 'htdocs' . DS . 'modules' . DS . 'prospamfilter' . DS . 'index.php');
        $this->filesystem->removeDirectory($this->paths->plesk . 'admin' . DS . 'htdocs' . DS . 'modules' . DS . 'prospamfilter' . DS . 'psf');
        unlink($this->paths->plesk . 'admin' . DS . 'plib' . DS . 'registry' . DS . 'EventListener' . DS . 'prospamfilter.php');
        $this->output->ok("Done");

        // Make new destination folder
        $this->output->info("Creating new folder (" . $this->paths->destination . ")..");
        if(!file_exists($this->paths->destination)){
            if (!mkdir($this->paths->destination, 0777, true)) {
                $this->output->error("Unable to create destination folder.");
                exit(1);
            }
        }
        $this->output->ok("Done");

        // Move all files to the new location.

        $this->output->info("Copying files from '" . $this->paths->base . "' to '" . $this->paths->destination . "'...");
        $command = 'XCOPY "'.$this->paths->base.'" "'.$this->paths->destination.'" /E';
        shell_exec($command);

        if (! file_exists(DEST_PATH . DS . 'public')) {
            throw new ShouldRetryException("Could not copy files successfully!");
        }

        $this->output->ok("Done");

        return true;
    }

    private function getVersion()
    {
        return trim(file_get_contents($this->paths->base . DS . "application" . DS . "version.txt"));
    }

    private function createConfigDirectory()
    {
        $directory = $this->paths->config;

        if (!file_exists($directory)) {
            @mkdir("{$directory}" . DS, 0755, true);
            if (!file_exists($directory)) {
                die("[ERROR] Unable to create config folder!");
            }
        }
    }

    private function symlinkImagesToWebDir()
    {
        $this->output->info("Symlinking Plesk images to webdir");
        $target = $this->paths->destination . DS . 'public';
        $link = $this->paths->plesk . 'admin' . DS . 'htdocs' . DS . 'modules' . DS . 'prospamfilter';
        $ret_val = $this->filesystem->symlinkDirectory($target, $link);
        $this->logger->info("[Install] Symlink (Plesk images -> webdir) returned with {$ret_val}");
    }

    private function symlinkFrontendToWebDir()
    {
        // Symlink frontend to PSA dir
        $this->output->info("Symlinking Plesk frontend to webdir");
        $target = $this->paths->destination . DS . 'frontend' . DS . 'index.php';
        $link = $this->paths->plesk . 'admin' . DS . 'htdocs' . DS . 'modules' . DS . 'prospamfilter' . DS . 'index.php';
        $ret_val = $this->filesystem->symlink($target, $link);
        $this->logger->info("[Install] Symlink (Plesk frontend -> webdir) returned with {$ret_val}");
    }

    private function symlinkImagesToPSFWebDir()
    {
        $this->output->info("Symlinking Plesk images to psf webdir");
        $target = $this->paths->plesk . 'admin' . DS . 'htdocs' . DS . 'modules' . DS . 'prospamfilter' . DS . 'images';
        $link = $this->paths->plesk . 'admin' . DS . 'htdocs' . DS . 'modules' . DS . 'prospamfilter' . DS . 'psf';
        $ret_val = $this->filesystem->symlinkDirectory($target, $link);
        $this->logger->info("[Install] Symlink (Plesk frontend -> webdir) returned with {$ret_val}");
    }

    private function setupHooks()
    {
        //Setup hooks
        $this->output->info("Setting up hooks.");
        $target = $this->paths->destination . DS . 'hooks' . DS . 'plesk.php';
        $link = $this->paths->plesk . 'admin' . DS . 'plib' . DS . 'registry' . DS . 'EventListener' . DS . 'prospamfilter.php';
        $ret_val = $this->filesystem->symlink($target, $link);
        $this->logger->info("[Install] Setting up hooks exited with {$ret_val}");
    }

    private function installCustomButtons($sqlPass, $sqlPort)
    {
        // Inject ourselves into the custom buttons

        // Install the Custom Buttons
        if (!function_exists('mysql_connect')) {
            $this->output->error("MySQL extension isn't loaded, skipping database-related tasks");
            return;
        }

        $mysql_connection = mysql_connect('localhost:' . $sqlPort, 'admin', $sqlPass);

        if (!empty($mysql_connection)) {
            if (!(@mysql_select_db('psa', $mysql_connection))) {
                die('ERROR SELECTING DB');
            }
        } else {
            die('ERROR CONNECTING TO MYSQL');
        }

        /**
         * Get changed brandname (in case of it was set)
         * @see https://trac.spamexperts.com/ticket/16804
         */
        $persistentBrandName = 'Professional Spam Filter';
        $res = mysql_query("SELECT DISTINCT `text` FROM `custom_buttons` WHERE url like '%prospamfilter%'");
        if (is_resource($res) && 1 == mysql_num_rows($res)) {
            $row = mysql_fetch_assoc($res);
            if (isset($row['text']) && $persistentBrandName <> $row['text']) {
                $persistentBrandName = $row['text'];
            }
        }

        // Clean ourselves up
        mysql_query("DELETE FROM `custom_buttons` WHERE url like '%prospamfilter%'");

        // Insert sidebar (for all levels)
        $query = "INSERT INTO `custom_buttons` (`id`, `sort_key`, `level`, `level_id`, `place`, `text`, `url`, `conhelp`, `options`, `file`) VALUES (0, 100, 1, 0, 'navigation', '" . addslashes($persistentBrandName) . "', '/modules/prospamfilter/', 'Manage your spamfiltered domains', 384, 'prospamfilter.png')";
        if (! mysql_query($query, $mysql_connection)) {
            echo mysql_error();
        }

        // Admin level
        $query = "INSERT INTO `custom_buttons` (`id`, `sort_key`, `level`, `level_id`, `place`, `text`, `url`, `conhelp`, `options`, `file`) VALUES (0, 100, 1, 0, 'admin', '" . addslashes($persistentBrandName) . "', '/modules/prospamfilter/', 'Manage your spamfiltered domains', 384, 'prospamfilter.png')";
        if (! mysql_query($query, $mysql_connection)) {
            echo mysql_error();
        }

        // Reseller level
        $query = "INSERT INTO `custom_buttons` (`id`, `sort_key`, `level`, `level_id`, `place`, `text`, `url`, `conhelp`, `options`, `file`) VALUES (0, 100, 1, 0, 'reseller', '" . addslashes($persistentBrandName) . "', '/modules/prospamfilter/', 'Manage your spamfiltered domains', 384, 'prospamfilter.png')";
        if (! mysql_query($query, $mysql_connection)) {
            echo mysql_error();
        }
    }

    private function removeSrcFolder()
    {
        $this->output->info("Removing temporary files");
        $this->filesystem->removeDirectory($this->paths->base);
        $this->output->ok("Done");
    }

    private function removeInstallFileAndDisplaySuccessMessage()
    {
        // Remove installer as we do not need it anymore.
        unlink($this->paths->destination . "/bin/install.php");
        $this->output->ok("\n\n***** Congratulations, Professional Spam Filter for Plesk Linux has been installed on your system! *****");
        $this->output->ok("If the addon is not configured yet, you should setup initial configuration in the admin part of the control panel before using its features.");
    }

}