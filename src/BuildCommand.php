<?php

namespace Console;

use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Command\Command;

class BuildCommand extends Command {
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
		try {
			$builder = new Builder( $input->getArgument( 'path' ) );

			$builder->build();

			$output->writeln( '<comment>Plugin built.</comment>' );
		} catch ( \Exception $e ) {
			$output->writeln( '<error>' . $e->getMessage() . '</error>' );
		}
	}
}