<?php


namespace clever_systems\mmm_builder\RenderPhp;


class PhpLines {
  /** @var PhpCodeInterface[] */
  protected $lines = [];

  /**
   * PhpComposite constructor.
   */
  public function __construct() {
  }

  /**
   * @param string|PhpCodeInterface $line
   * @return $this
   */
  public function addLine($line) {
    $this->lines[] = $line;
    return $this;
  }

  /**
   * @return string
   */
  public function __toString() {
    return implode("\n", $this->lines);
  }
}
