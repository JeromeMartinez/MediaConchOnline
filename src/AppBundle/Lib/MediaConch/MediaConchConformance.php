<?php

namespace AppBundle\Lib\MediaConch;

use Symfony\Component\Process\ProcessBuilder;

class MediaConchConformance extends MediaConch
{
    public function run()
    {
        $builder = new ProcessBuilder();
        $process = $builder->setPrefix($this->MediaConch)
            ->add($this->source)
            ->getProcess();
        $process->setEnv(array('LANG' => 'en_US.UTF-8'));
        $process->run();

        if ($process->isSuccessful()) {
            $this->success = true;
            $this->output = $process->getOutput();
        }

        return $this;
    }
}
