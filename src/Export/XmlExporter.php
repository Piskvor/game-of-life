<?php
declare(strict_types=1);

namespace Piskvor\Export;

trait XmlExporter
{
    public function export(string $filename): bool
    {
        $writer = new \XMLWriter();
        $writer->openURI($filename);
        $writer->startDocument('1.0', 'UTF-8');
        $writer->setIndent(true);
        $writer->startElement('life');
        $writer->startElement('world');
        $writer->writeElement('cells', (string)$this->getEdgeSize());
        $writer->writeElement('species', (string)$this->getSpeciesCount());
        $writer->writeElement('iterations', (string)$this->getMaxIterations());
        $writer->endElement();
        $writer->startElement('organisms');
        $organisms = $this->getAllOrganisms();
        unset($board);
        foreach ($organisms as $id => $organism) {
            $writer->startElement('organism');
            $writer->writeElement('x_pos', (string)$organism['x']);
            $writer->writeElement('y_pos', (string)$organism['y']);
            $writer->writeElement('species', $organism['species']);
            $writer->endElement();
            unset($organisms[$id]);
        }
        $writer->endElement();
        $writer->endElement();
        $result = $writer->endDocument();
        $writer->flush();
        return (bool)$result;
    }
}