<?php

namespace Console;

use ZipArchive;

class Builder {
	private $working_directory;
	private $plugin_data;
	private $ignore_filename = '.build_ignore';
	public $build_directory = 'build';

	public function __construct( $working_directory = '.' ) {
		if ( ! class_exists( 'ZipArchive' ) ) {
			throw new RuntimeException( 'The Zip PHP extension is not installed. Please install it and try again.' );
		}

		$this->working_directory = $working_directory;

		$this->plugin_data = $this->getPluginData();

		$this->ignore_list = $this->getIgnoreList();
	}

	/**
	 * Build plugin.
	 *
	 * @throws \Exception
	 */
	public function build() {
		$this->createBuildFolderIfNotExist();

		$this->createZipArchive();

		$this->getDirContents( $this->working_directory );

		$this->zip->close();
	}

	/**
	 * Make new build folder inside plugin if this folder is not exist.
	 *
	 * @throws \Exception
	 */
	private function createBuildFolderIfNotExist() {
		$dir = $this->working_directory . DIRECTORY_SEPARATOR . $this->build_directory;

		if ( ! file_exists( $dir ) && ! is_dir( $dir ) ) {
			if ( ! mkdir( $dir ) && ! is_dir( $dir ) ) {
				throw new \Exception( sprintf( 'Directory "%s" was not created', $dir ) );
			}
		}
	}

	/**
	 * Return plugin name and version.
	 *
	 * @return array
	 * @throws \Exception
	 */
	private function getPluginData() {
		$default_headers = [
			'Name'    => 'Plugin Name',
			'Version' => 'Version',
		];

		$files = scandir( $this->working_directory, SCANDIR_SORT_NONE );
		foreach ( $files as $key => $value ) {
			$path = realpath( $this->working_directory . DIRECTORY_SEPARATOR . $value );

			if ( ! is_dir( $path ) ) {
				$data = $this->get_file_data( $path, $default_headers );
				if ( ! empty ( $data['Name'] ) ) {
					return $data;
				}
			}
		}

		throw new \Exception( 'Valid plugin not found.' );
	}

	/**
	 * Return plugins header from file if they exist.
	 *
	 * @param $file
	 * @param $default_headers
	 *
	 * @return array
	 */
	private function get_file_data( $file, $default_headers ) {
		// We don't need to write to the file, so just open for reading.
		$fp = fopen( $file, 'r' );

		// Pull only the first 8kiB of the file in.
		$file_data = fread( $fp, 8192 );

		// PHP will close file handle, but we are good citizens.
		fclose( $fp );

		// Make sure we catch CR-only line endings.
		$file_data = str_replace( "\r", "\n", $file_data );

		$all_headers = $default_headers;

		foreach ( $all_headers as $field => $regex ) {
			if ( preg_match( '/^[ \t\/*#@]*' . preg_quote( $regex, '/' ) . ':(.*)$/mi', $file_data, $match ) && $match[1] ) {
				$all_headers[ $field ] = trim( preg_replace( "/\s*(?:\*\/|\?>).*/", '', $match[1] ) );
			} else {
				$all_headers[ $field ] = '';
			}
		}

		return $all_headers;
	}

	/**
	 * Create new archive for plugin.
	 *
	 * @throws \Exception
	 */
	private function createZipArchive() {
		$this->zip = new ZipArchive();
		$path      = $this->getPluginPath();

		if ( file_exists( $path ) ) {
			throw new \Exception( 'Build already exist.' );
		}

		if ( $this->zip->open( $path, ZipArchive::CREATE ) !== true ) {
			throw new \Exception( "Can't create <$path>\n" );
		}
	}

	/**
	 * Return full path with name to plugin archive.
	 *
	 * @return string
	 */
	private function getPluginPath() {
		return $this->working_directory . DIRECTORY_SEPARATOR .
		       $this->build_directory . DIRECTORY_SEPARATOR .
		       $this->getPluginName();
	}

	/**
	 * Return name of plugin archive.
	 *
	 * @return string
	 */
	private function getPluginName() {
		$name = str_replace( ' ', '_', strtolower( $this->plugin_data['Name'] ) );

		return $name . '_' . $this->plugin_data['Version'] . '.zip';
	}

	/**
	 * Get list of ignored files.
	 *
	 * @return array
	 */
	private function getIgnoreList() {
		$file_name = $this->working_directory . DIRECTORY_SEPARATOR . $this->ignore_filename;
		if ( ! file_exists( $file_name ) ) {
			return [];
		}

		$list = explode( "\n", file_get_contents( $file_name ) );

		return array_map( 'trim', $list );
	}

	/**
	 * Recursive function. Recursively get files and folders.
	 * Check if they are not ignored and add to the archive.
	 *
	 * @param $build_path
	 * @param string $zip_path
	 */
	private function getDirContents( $build_path, $zip_path = '' ) {
		$files = scandir( $build_path, SCANDIR_SORT_NONE );
		foreach ( $files as $key => $value ) {
			$path = realpath( $build_path . DIRECTORY_SEPARATOR . $value );

			if ( $this->isIgnored( $value ) ) {
				continue;
			}

			if ( ! is_dir( $path ) ) {
				$this->zip->addFile( $path, $zip_path . $value );
			} else if ( $value !== '.' && $value !== '..' ) {
				$this->getDirContents( $path, $zip_path . $value . DIRECTORY_SEPARATOR );
			}
		}
	}

	/**
	 * Check if file is ignored.
	 *
	 * @param $path
	 *
	 * @return bool
	 */
	private function isIgnored( $path ) {
		return in_array( $path, $this->ignore_list, true );
	}

}