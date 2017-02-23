<?php


namespace clever_systems\mmm_builder\Commands;


class CopyFile extends AbstractTwoFileOp implements CommandInterface {

  public function execute(array &$results, $simulate = FALSE) {
    if (!$simulate) {
      copy($this->source, $this->target);
    }
    $results[$this->target] = file_get_contents($this->source);
  }
  
}
