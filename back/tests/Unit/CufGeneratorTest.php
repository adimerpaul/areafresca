<?php

namespace Tests\Unit;

use App\Services\Siat\CufGenerator;
use PHPUnit\Framework\TestCase;

class CufGeneratorTest extends TestCase
{
    public function test_it_converts_a_modulo_11_remainder_of_ten_to_one(): void
    {
        $generator = new CufGenerator();

        $cuf = $generator->generate(
            nit: '3544875019',
            timestamp: '20260731214833368',
            branch: 0,
            modality: 1,
            emission: 1,
            invoice: 17,
            pos: 0,
            control: '7204D7D6170BF74',
        );

        $this->assertSame(
            'F28CD95894ED9F46162B80E18B4BCC5C150BB670A17204D7D6170BF74',
            $cuf,
        );
    }
}
