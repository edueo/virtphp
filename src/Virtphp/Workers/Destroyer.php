<?php
/*
 * This file is part of VirtPHP.
 *
 * (c) Jordan Kasper <github @jakerella>
 *     Ben Ramsey <github @ramsey>
 *     Jacques Woodcock <github @jwoodcock>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Virtphp\Workers;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use InvalidArgumentException;


class Destroyer extends AbstractWorker
{

    /**
     * @var InputInterface
     */
    protected $input = null;
    /**
     * @var OutputInterface
     */
    protected $output = null;
    /**
     * @var string
     */
    private $rootPath;

    /**
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param null $rootPath Path to root
     */
    public function __construct(InputInterface $input, OutputInterface $output, $rootPath = null)
    {
        $this->input = $input;
        $this->output = $output;
        $this->setRootPath($rootPath);
    }

    /**
     * getRootPath
     *
     * @return string Root path
     */
    public function getRootPath()
    {
        return $this->rootPath;
    }

    /**
     * setRootPath
     *
     * @param string $path Path to root
     */
    public function setRootPath($path = ".")
    {
        $this->rootPath = strval($path);
    }


    /**
     * Function is the guts of the worker, reading the provided
     * options and destroying the old virtual env.
     *
     * @return boolean Whether or not the action was successful
     */
    public function execute()
    {

        if (!$this->getFilesystem()->exists($this->rootPath)) {
            $this->output->writeln('<error>This directory does not exist!</error>');

            return false;
        }

        if (!$this->getFilesystem()->exists($this->rootPath.DIRECTORY_SEPARATOR.'.virtphp')) {
            $this->output->writeln('<error>This directory does not contain a valid VirtPHP environment!</error>');

            return false;
        }

        $this->removeStructure();
        $this->removeFromList();

        return true;
    }

    /**
     * Removes the directory structure for the old virtual env
     */
    protected function removeStructure()
    {
        $this->output->writeln('<info>Removing directory structure</info>');
        $this->getFilesystem()->remove($this->rootPath);
    }

    /**
     * Removes the env from the environments.json file.
     */
    protected function removeFromList()
    {
        $this->output->writeln('<info>Removing environment from list</info>');
        // if not, we create it then add this environment and path
        $envPath = $_SERVER['HOME'] . DIRECTORY_SEPARATOR .  '.virtphp';
        $envFile = 'environments.json';

        if ($this->getFilesystem()->exists($envPath . DIRECTORY_SEPARATOR . $envFile)) {
            // get contents, convert to array, add this env and path
            $envContents = $this->getFilesystem()->getContents(
                $envPath . DIRECTORY_SEPARATOR . $envFile
            );

            // get the env name from path
            $path = $this->getRootPath();
            // make sure the trailing / is removed if autocompleted
            if (substr($path, -1) === '/') {
                $path = substr($path, 0, -1);
            }
            // Convert to an array if full path
            $path = explode(DIRECTORY_SEPARATOR, $path);
            if (is_array($path)) {
                // grab the last entry which is the name we are looking for
                $path = $path[count($path) - 1 ];
            }

            // Convert the contents to array
            $envList = json_decode($envContents, true);
            if (isset($envList[$path])) {
                $this->output->writeln(
                    '<info>Found path and removed from list. '
                    . $path . '</info>'
                );
                unset($envList[$path]);
            } else {
                $this->output->writeln(
                    '<info>No matching environments in list archive. '
                    . $path . '</info>'
                );
            }

            $this->getFilesystem()->dumpFile(
                $envPath . DIRECTORY_SEPARATOR . $envFile,
                json_encode($envList)
            );
        }
    }
}
