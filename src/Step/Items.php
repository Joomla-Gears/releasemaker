<?php
/**
 * @package    AkeebaReleaseMaker
 * @copyright  Copyright (c)2012-2021 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license    GNU General Public License version 3, or later
 */

namespace Akeeba\ReleaseMaker\Step;

use Akeeba\ReleaseMaker\Configuration;
use Akeeba\ReleaseMaker\Exception\FatalProblem;
use Akeeba\ReleaseMaker\Utils\ARS;
use stdClass;

class Items extends AbstractStep
{
	/** @var ARS The ARS connector class */
	private $arsConnector = null;

	/** @var stdClass The release we will be saving items to */
	private $release = null;

	private $publishInfo = [
		'release' => null,
		'core'    => [],
		'pro'     => [],
		'pdf'     => [],
	];

	public function execute(): void
	{
		$this->io->section('Creating or updating items');

		$this->io->writeln("<info>Getting release information</info>");

		$this->retrieveReleaseInfo();

		$this->io->text(sprintf("Retrieved information for release %u", $this->release->id));

		$this->publishInfo['release'] = $this->release;

		$this->io->writeln("<info>Creating items for Core files</info>");

		$this->deployFiles('core');

		$this->io->writeln("<info>Creating items for Pro files</info>");

		$this->deployFiles('pro');

		$this->io->writeln("<info>Creating items for PDF files</info>");

		$this->deployFiles('core', true);

		$this->io->writeln("<info>Saving publish information</info>");

		$conf = Configuration::getInstance();
		$conf->set('volatile.publishInfo', $this->publishInfo);

		$this->io->newLine();
	}

	/**
	 * Set $this->release to the record for the release we will be using to save items to.
	 */
	private function retrieveReleaseInfo(): void
	{
		$conf = Configuration::getInstance();

		$this->arsConnector = new ARS([
			'host'     => $conf->get('common.arsapiurl', ''),
			'username' => $conf->get('common.username', ''),
			'password' => $conf->get('common.password', ''),
			'apiToken' => $conf->get('common.token', ''),
		]);

		$category = $conf->get('common.category', 0);
		$version  = $conf->get('common.version', 0);

		$this->release = $this->arsConnector->getRelease($category, $version);
	}

	private function deployFiles(string $prefix = 'core', bool $isPdf = false): void
	{
		// Get the files
		$publishArea = $prefix;

		if ($isPdf || ($prefix == 'pdf'))
		{
			$publishArea = 'pdf';
			$prefix      = 'core';
			$isPdf       = true;
		}

		$this->publishInfo[$publishArea] = [];

		$conf      = Configuration::getInstance();
		$files     = $conf->get('volatile.files');
		$coreFiles = $files[$prefix] ?? [];

		if ($isPdf)
		{
			$coreFiles = $files['pdf'] ?? [];
		}

		if (empty($coreFiles))
		{
			$this->io->note(sprintf('No %s files found', $prefix));

			return;
		}

		$access = $conf->get("$prefix.access", "1");

		foreach ($coreFiles as $filename)
		{
			// Get the filename and path used in ARS
			$this->io->text(sprintf("Creating/updating item for %s", $filename));

			$type = $conf->get($prefix . '.method', $conf->get('common.update.method', 'sftp'));

			switch ($type)
			{
				// TODO Add GitHub case

				case 's3':
					$version     = $conf->get('common.version');
					$reldir      = $conf->get($prefix . '.s3.reldir');
					$cdnHostname = $conf->get($prefix . '.s3.cdnhostname');
					$destName    = $version . '/' . basename($filename);

					if (empty($cdnHostname))
					{
						$fileOrURL = 's3://' . $reldir . '/' . $destName;
						$type      = 'file';
					}
					else
					{
						$directory = $conf->get($prefix . '.s3.directory', $conf->get('common.update.s3.directory', ''));
						$fileOrURL = 'https://' . $cdnHostname . '/' . $directory . '/' . $destName;
						$type      = 'link';
					}

					break;

				case 'ftp':
				case 'ftpcurl':
				case 'ftps':
				case 'ftpscurl':
				case 'sftp':
				case 'sftpcurl':
				default:
					$version   = $conf->get('common.version');
					$fileOrURL = $version . '/' . basename($filename);
					$type      = 'file';
					break;
			}

			// Fetch a record of the file
			$item = $this->arsConnector->getItem($this->release->id, $type, $fileOrURL);
			$oldId = $item->id;

			$item->release_id = $this->release->id;
			$item->type       = $type;
			$item->filename   = ($type == 'file') ? $fileOrURL : '';
			$item->url        = ($type == 'link') ? $fileOrURL : '';
			$item->access     = $access;
			$item->published  = 0;

			$this->publishInfo[$publishArea][] = $item;

			$result = $this->arsConnector->saveItem((array) $item);

			if ($result !== 'false')
			{
				$action = $oldId ? "updated" : "created";
				$itemMeta = json_decode($result);

				$this->io->success(sprintf("Item %u has been $action", $itemMeta->id));
			}
            else {
                $this->io->error("Failed to create item");

                throw new FatalProblem(sprintf("Failed to create item for file %s, uploaded via %s", $filename, $type), 40);
            }
        }
	}
}
