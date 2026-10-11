<?php

declare(strict_types=1);

namespace Akira\Efatura\Dfa;

use Akira\Efatura\Contracts\DfaRenderer;
use Dompdf\Adapter\CPDF;
use Dompdf\Dompdf;

final readonly class DompdfDfaRenderer implements DfaRenderer
{
    public function __construct(
        private DfaView $view,
        private DompdfOptions $options,
        private LibraryRequirement $library = new LibraryRequirement('dompdf/dompdf', Dompdf::class),
    ) {}

    public function render(DfaData $data): RenderedDfa
    {
        $this->library->ensureInstalled();

        $html   = $this->view->render($data);
        $dompdf = new Dompdf($this->options->make());
        $canvas = new CPDF(DompdfOptions::PAPER, 'portrait', $dompdf);
        $dompdf->setCanvas($canvas);
        $dompdf->getFontMetrics()->setCanvas($canvas);

        $issuedAt = 'D:' . $data->content->issuedAt->utc()->format('YmdHis') . 'Z';
        $dompdf->addInfo('Title', 'DFA ' . $data->iud);
        $dompdf->addInfo('CreationDate', $issuedAt);
        $dompdf->addInfo('ModDate', $issuedAt);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();
        $canvas->get_cpdf()->fileIdentifier = md5($html);

        return new RenderedDfa((string) $dompdf->output(), $data->filename(), $data->iud, $data->mode);
    }
}
