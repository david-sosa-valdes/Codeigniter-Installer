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
     * Codeigniter version
     * @var integer
     */
    protected $_version;

    /**
     * Installation path
     * @var string
     */
    protected $_path;

    /**
     * Codeigniter URL
     * @var string
     */
    protected $_url = 'http://github.com/bcit-ci/CodeIgniter/archive/{version}.zip';

    /**
     * Installation directory path
     * @var string
     */
    protected $_directory;

    /**
     * Zip file created
     * @var string
     */
    protected $_file;

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
                InputArgument::REQUIRED,
                'Application name'
            )
            ->addArgument(
                'version', 
                InputArgument::OPTIONAL,
                'CodeIgniter version required',
                '3.0.6'
            )
            ->addOption(
                'interactive',
                'i',
                InputOption::VALUE_NONE,
                'Interactive mode'
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

        $directory = getcwd().'/'.$input->getArgument('name');
        $this->_version = $input->getArgument('version');

        $question = new Question('Application path [<comment>'.$directory.'</comment>]: ', $directory);
        
        $question->setValidator(function ($test_directory) {
            if (is_dir($test_directory)) {
                throw new \RuntimeException(
                    'Application already exists!'
                );
            }
            return $test_directory;
        });

        $this->_directory = ($input->getOption('interactive'))
            ? $helper->ask($input, $output, $question)
            : $directory;

        $this->_path = realpath(dirname($this->_directory));

        $output->writeln('<info>Creating CI application...</info>');

        $this->create_zipfile()
             ->download_CI()
             ->extract()
             ->clean();

        $output->writeln('<comment>Application ready! Build something amazing.</comment>');
    }

    /**
     * Generate a random temporary filename.
     *
     * @return string
     */    
    protected function create_zipfile()
    {
        $this->_file = $this->_path.'/codeigniter_'.hash('md5', time()).'.zip';
        return $this;
    }

    /**
     * Download the temporary Zip to the given file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function download_CI()
    {
        $response = (new Client)->get(str_replace('{version}', $this->_version, $this->_url));
        file_put_contents($this->_file, $response->getBody());
        return $this;
    }

    /**
     * Extract the zip file into the given directory.
     *
     * @param  string  $zipFile
     * @param  string  $directory
     * @return $this
     */
    protected function extract()
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
     * Clean-up the Zip file.
     *
     * @param  string  $zipFile
     * @return $this
     */
    protected function clean()
    {
        @chmod($this->_file, 0777);
        @unlink($this->_file);
        return $this;
    }
}