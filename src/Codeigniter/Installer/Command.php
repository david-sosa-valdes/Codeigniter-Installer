<?php
namespace Codeigniter\Installer;

use Symfony\Component\Process\Process;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOExceptionInterface;
use GuzzleHttp\Client;

/**
 * Installer Command Class
 *
 * @author     David Sosa Valdes <https://github.com/davidsosavaldes>
 * @license    MIT License
 * @link       https://github.com/davidsosavaldes/Codeigniter-Installer
 * @copyright  Copyright (c) 2016, David Sosa Valdes.
 * @version    1.0.0
 */
class Command extends SymfonyCommand
{
    /**
     * Zip file created
     * @var string
     */
    private $_file;

    /**
     * Codeigniter version
     * @var integer
     */
    private $_version;

    /**
     * Installation path
     * @var string
     */
    private $_path;

    /**
     * Installation directory path
     * @var string
     */
    private $_directory;

    /**
     * @var \Symfony\Filesystem
     */
    private $_filesystem;

    /**
     * Codeigniter URL
     * @var string
     */
    const URL = 'http://github.com/bcit-ci/CodeIgniter/archive/{version}.zip';

    /**
     * Configure the command options.
     *
     * @return void
     */
    protected function configure()
    {
        $this
            ->setName('new')
            ->setDescription('Create a new Codeigniter application.')
            ->addArgument(
                'name', 
                InputArgument::OPTIONAL,
                'Application name'
            )
            ->addArgument(
                'version', 
                InputArgument::OPTIONAL,
                'CodeIgniter version required',
                '3.0.6'
            )
            ->addOption(
                'secure',
                NULL,
                InputOption::VALUE_NONE,
                'If set, the task will create a secure CI installation'
            );
    }	

    /**
     * Execute the command.
     *
     * @param  InputInterface  $input
     * @param  OutputInterface  $output
     * @return void
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $helper = $this->getHelper('question');

        $this->_version = $input->getArgument('version');
        $this->_filesystem = new Filesystem();
        
        $directory = getcwd().'/'.$input->getArgument('name');
        $question  = new Question('Application name: ', $directory);
        
        $question->setValidator(function ($test_directory) {
            if (is_dir($test_directory)) {
                throw new \RuntimeException(
                    'Application already exists!'
                );
            }
            return $test_directory;
        });

        $this->_directory = ($input->getArgument('name'))
            ? $directory
            : $helper->ask($input, $output, $question);

        $this->_path = realpath(dirname($this->_directory));

        $output->writeln('<info>Creating CI application...</info>');

        $this->create_file()
             ->download_CI()
             ->extract()
             ->clean();

        $input->getOption('secure') && $this->secure();

        $output->writeln('<comment>Application ready! Build something amazing.</comment>');
    }

    /**
     * Generate a random temporary filename.
     */    
    private function create_file()
    {
        $this->_file = $this->_path.'/codeigniter_'.hash('md5', time()).'.zip';
        return $this;
    }

    /**
     * Download the temporary Zip to the given file.
     */
    private function download_CI()
    {
        $response = (new Client)->get(str_replace('{version}', $this->_version, self::URL));
        file_put_contents($this->_file, $response->getBody());
        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     */
    private function extract()
    {
        $archive = new \ZipArchive;

        if($archive->open($this->_file) !== TRUE)
        {
            throw new \RuntimeException("Error extracting the file.");
        }
        $archive->extractTo($this->_path);
        $archive->close(); 
        
        /**
         * @todo make recursive method
         * 
         * - http://stackoverflow.com/questions/6682491/extract-files-in-a-zip-to-root-of-a-folder 
         */
        $old_path = $this->_path.'/CodeIgniter-'.$this->_version;

        if (! file_exists($old_path)) 
        {
            throw new \RuntimeException("Error Processing file request". $old_path);
        }
        else 
        {
            rename($old_path, $this->_directory);
        }
        return $this;
    }

    /**
     * Clean-up the zip file
     */
    private function clean()
    {        
        $directory = $this->_directory.DIRECTORY_SEPARATOR;

        $this->_filesystem->chmod($this->_file, 0777);
        $this->_filesystem->remove($this->_file);

        $filenames = ['.gitignore', 'composer.json', 'contributing.md', 'readme.rst', 'user_guide'];
        // Remove recursive strategy if filename is a directory
        foreach ($filenames as $filename) 
        {
            $this->_filesystem->exists($component = $directory.$filename) 
                && $this->_filesystem->remove($component);
        }       
        return $this;
    }

    /**
     * Create a secure CI installation
     */
    private function secure()
    {
        // Create htaccess
        $line = "RewriteEngine On\n";
        $line.= "RewriteCond %{REQUEST_FILENAME} !-f\n";
        $line.= "RewriteCond %{REQUEST_FILENAME} !-d\n";
        $line.= "RewriteRule ^(.*)$ index.php/$1 [L]\n";
        $this->_filesystem->mkdir($this->_directory.'/public');
        $this->_filesystem->dumpFile($this->_directory.'/public/.htaccess', $line);

        // Replace index paths
        $find = array_map(
            function($key) {return  "/^.*(= '$key').*$/m";}, 
            ["system", "application"]
        );
        $replace = array_map(
            function($block) {return  "\t".str_replace('"',"'", $block);}, 
            ['$system_path = "../system";', '$application_folder = "../application";']
        );
        $this->_filesystem->dumpFile(
            $this->_directory.'/public/index.php',
            preg_replace($find, $replace, file_get_contents($this->_directory.'/index.php'))
        );
        $this->_filesystem->remove($this->_directory.'/index.php');

        // Config the index page
        $block = '$config["index_page"] = "";';
        $this->_filesystem->dumpFile(
            $this->_directory.'/application/config/config.php', 
            preg_replace(
                "/^.*('index_page').*$/m", 
                str_replace('"', "'", $block), 
                file_get_contents($this->_directory.'/application/config/config.php')
            )
        );
        return $this;
    }
}