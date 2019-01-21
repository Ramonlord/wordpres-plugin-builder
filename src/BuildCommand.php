<?php

namespace Console;

use ZipArchive;
use RuntimeException;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class BuildCommand extends Command {
	private $debug = true;
	private $working_directory = '.';
	private $ignore_list = [];
	private $build_dir = 'build';
	private $zip;

	/**
	 * Configure the command options.
	 *
	 * @return void
	 */
	protected function configure() {
		$this
			->setName( 'build' )
			->setDescription( 'Build plugin from current directory.' )
			->addArgument( 'path', InputArgument::OPTIONAL, 'Path to plugin.' );
		//->addOption('dev', null, InputOption::VALUE_NONE, 'Installs the latest "development" release')
		//->addOption('force', 'f', InputOption::VALUE_NONE, 'Forces install even if the directory already exists');
	}

	/**
	 * Execute the command.
	 *
	 * @param  \Symfony\Component\Console\Input\InputInterface $input
	 * @param  \Symfony\Component\Console\Output\OutputInterface $output
	 *
	 * @return void
	 */
	protected function execute( InputInterface $input, OutputInterface $output ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( 'The Zip PHP extension is not installed. Please install it and try again.' );
		}

		$this->zip = new ZipArchive();

		$this->working_directory = $input->getArgument( 'path' ) ?: '.';

		$this->createBuildFolder();

		$this->ignore_list = $this->getIgnoreList();

		$this->createZipArchive();

		$this->getDirContents( $this->working_directory );

		$this->zip->close();

		$output->writeln( '<comment>Application ready! Build something amazing.</comment>' );
	}

	private function createZipArchive() {
		$path = $this->working_directory . DIRECTORY_SEPARATOR . $this->build_dir . DIRECTORY_SEPARATOR . 'test.zip';

		$this->d( $path );

		if ( $this->zip->open( $path, ZipArchive::CREATE ) !== true ) {
			exit( "Can't create <$path>\n" );
		}
	}

	private function createBuildFolder() {
		$dir = $this->working_directory . DIRECTORY_SEPARATOR . $this->build_dir;

		if ( ! file_exists( $dir ) && ! is_dir( $dir ) ) {
			if ( ! mkdir( $dir ) && ! is_dir( $dir ) ) {
				sprintf( 'Directory "%s" was not created', $dir );
			}
		}
	}

	private function getIgnoreList() {
		$file_name = $this->working_directory . DIRECTORY_SEPARATOR . '.build_ignore';
		if ( ! file_exists( $file_name ) ) {
			return [];
		}

		$list = explode( "\n", file_get_contents( $file_name ) );

		return array_map( 'trim', $list );
	}

	private function getDirContents( $build_path, $zip_path = '' ) {
		$files = scandir( $build_path, SCANDIR_SORT_NONE );
		foreach ( $files as $key => $value ) {
			$path = realpath( $build_path . DIRECTORY_SEPARATOR . $value );

			if ( $this->isIgnored( $value ) ) {
				continue;
			}

			$this->d( $path . "\n" );
			$this->d( $zip_path . "\n" );

			if ( ! is_dir( $path ) ) {
				$this->zip->addFile( $path, $zip_path . $value );
			} else if ( $value !== '.' && $value !== '..' ) {
				$this->getDirContents( $path, $zip_path . $value . DIRECTORY_SEPARATOR );
			}
		}
	}

	private function isIgnored( $path ) {
		return in_array( $path, $this->ignore_list, true );
	}

	private function d( $msg ) {
		if ( $this->debug ) {
			print_r( "\n" );
			print_r( $msg );
		}
	}

	/**
	 * Clean-up the Zip file.
	 *
	 * @param  string $zipFile
	 *
	 * @return $this
	 */
	protected function cleanUp( $zipFile ) {
		@chmod( $zipFile, 0777 );
		@unlink( $zipFile );

		return $this;
	}
}