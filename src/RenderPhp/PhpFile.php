<?php


namespace clever_systems\mmm_builder\RenderPhp;


class PhpFile implements PhpCodeInterface {
  /** @var PhpLines */
  protected $header;
  /** @var PhpLines */
  protected $body;
  /** @var PhpLines */
  protected $footer;

  /**
   * PhpFile constructor.
   */
  public function __construct() {
    $this->header = new PhpLines();
    $this->body = new PhpLines();
    $this->footer = new PhpLines();
  }

  public function addToHeader(PhpCodeInterface $line) {
    $this->header->addLine($line);
  }

  public function addToBody(PhpCodeInterface $line) {
    $this->body->addLine($line);
  }

  public function addToFooter(PhpCodeInterface $line) {
    $this->footer->addLine($line);
  }

  public function __toString() {
    return "<?php\n" . $this->header . $this->body . $this->footer;
  }

}