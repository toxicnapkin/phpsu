<?php
declare(strict_types=1);

namespace PHPSu\Cli;

use Exception;
use function in_array;
use PHPSu\Config\AppInstance;
use PHPSu\Helper\StringHelper;
use PHPSu\Options\SshOptions;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;

final class SshCliCommand extends AbstractCliCommand
{
    /** @var null|string[] */
    private $instances;

    /**
     * @return void
     */
    protected function configure()
    {
        $this->setName('ssh')
            ->setDescription('create SSH Connection')
            ->setHelp('Connect to AppInstance via SSH.')
            ->addOption('dry-run', 'd', InputOption::VALUE_NONE, 'Only show commands that would be run.')
            ->addOption('from', 'f', InputOption::VALUE_OPTIONAL, 'Only show commands that would be run.', 'local')
            ->addArgument('destination', InputArgument::REQUIRED, 'The Destination AppInstance.')
            ->addArgument('commands', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'The Destination AppInstance.', []);
    }

    /**
     * @return void
     */
    protected function initialize(InputInterface $input, OutputInterface $output)
    {
        $default = $input->hasArgument('destination') ? $this->getArgument($input, 'destination') ?? '' : '';
        $input->setArgument(
            'destination',
            StringHelper::findStringInArray($default, $this->getAppInstancesWithHost()) ?: $default
        );
    }

    /**
     * @return void
     * @throws Exception
     */
    protected function interact(InputInterface $input, OutputInterface $output)
    {
        $default = $input->hasArgument('destination') ? $this->getArgument($input, 'destination') : '';
        if (empty($this->getAppInstancesWithHost())) {
            throw new Exception('You need to define at least one AppInstance besides local');
        }
        if (!in_array($default, $this->getAppInstancesWithHost(), true)) {
            $question = new ChoiceQuestion('Please select one of the AppInstances', $this->getAppInstancesWithHost());
            $question->setErrorMessage('AppInstance %s not found in Config.');
            $destination = $this->getHelper('question')->ask($input, $output, $question);
            $output->writeln('You selected: ' . $destination);
            $input->setArgument('destination', $destination);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $destination = $this->getArgument($input, 'destination');
        $currentHost = $this->getOption($input, 'from');
        $commandArray = $this->getArgument($input, 'commands');
        return $this->controller->ssh(
            $output,
            $this->configurationLoader->getConfig(),
            (new SshOptions($destination))
                ->setCurrentHost($currentHost)
                ->setCommand(implode(' ', $commandArray))
                ->setDryRun((bool)$input->getOption('dry-run'))
        );
    }

    /**
     * @return string[]
     */
    protected function getAppInstancesWithHost(): array
    {
        if ($this->instances === null) {
            $this->instances = $this->configurationLoader->getConfig()->getAppInstanceNames(function (AppInstance $instance) {
                return $instance->getHost() !== '';
            });
        }
        return $this->instances;
    }
}
