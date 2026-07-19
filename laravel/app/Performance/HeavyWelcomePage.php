<?php

namespace App\Performance;

use App\Performance\Dependency\Dependency01;
use App\Performance\Dependency\Dependency02;
use App\Performance\Dependency\Dependency03;
use App\Performance\Dependency\Dependency04;
use App\Performance\Dependency\Dependency05;
use App\Performance\Dependency\Dependency06;
use App\Performance\Dependency\Dependency07;
use App\Performance\Dependency\Dependency08;
use App\Performance\Dependency\Dependency09;
use App\Performance\Dependency\Dependency10;
use App\Performance\Dependency\Dependency11;
use App\Performance\Dependency\Dependency12;
use App\Performance\Dependency\Dependency13;
use App\Performance\Dependency\Dependency14;
use App\Performance\Dependency\Dependency15;
use App\Performance\Dependency\Dependency16;
use App\Performance\Dependency\Dependency17;
use App\Performance\Dependency\Dependency18;
use App\Performance\Dependency\Dependency19;
use App\Performance\Dependency\Dependency20;
use App\Performance\Dependency\Dependency21;
use App\Performance\Dependency\Dependency22;
use App\Performance\Dependency\Dependency23;
use App\Performance\Dependency\Dependency24;
use App\Performance\Dependency\Dependency25;
use App\Performance\Dependency\Dependency26;
use App\Performance\Dependency\Dependency27;
use App\Performance\Dependency\Dependency28;
use App\Performance\Dependency\Dependency29;
use App\Performance\Dependency\Dependency30;
use App\Performance\Dependency\Dependency31;
use App\Performance\Dependency\Dependency32;
use App\Performance\Dependency\Dependency33;
use App\Performance\Dependency\Dependency34;
use App\Performance\Dependency\Dependency35;
use App\Performance\Dependency\Dependency36;

/**
 * Laravel の自動解決で 36 個の依存を生成する、Welcome ページ計測用サービス。
 * 各依存を別ファイルに置き、実際にオートロード・コンパイル対象を増やす。
 */
final class HeavyWelcomePage
{
    public function __construct(
        private readonly Dependency01 $dependency01,
        private readonly Dependency02 $dependency02,
        private readonly Dependency03 $dependency03,
        private readonly Dependency04 $dependency04,
        private readonly Dependency05 $dependency05,
        private readonly Dependency06 $dependency06,
        private readonly Dependency07 $dependency07,
        private readonly Dependency08 $dependency08,
        private readonly Dependency09 $dependency09,
        private readonly Dependency10 $dependency10,
        private readonly Dependency11 $dependency11,
        private readonly Dependency12 $dependency12,
        private readonly Dependency13 $dependency13,
        private readonly Dependency14 $dependency14,
        private readonly Dependency15 $dependency15,
        private readonly Dependency16 $dependency16,
        private readonly Dependency17 $dependency17,
        private readonly Dependency18 $dependency18,
        private readonly Dependency19 $dependency19,
        private readonly Dependency20 $dependency20,
        private readonly Dependency21 $dependency21,
        private readonly Dependency22 $dependency22,
        private readonly Dependency23 $dependency23,
        private readonly Dependency24 $dependency24,
        private readonly Dependency25 $dependency25,
        private readonly Dependency26 $dependency26,
        private readonly Dependency27 $dependency27,
        private readonly Dependency28 $dependency28,
        private readonly Dependency29 $dependency29,
        private readonly Dependency30 $dependency30,
        private readonly Dependency31 $dependency31,
        private readonly Dependency32 $dependency32,
        private readonly Dependency33 $dependency33,
        private readonly Dependency34 $dependency34,
        private readonly Dependency35 $dependency35,
        private readonly Dependency36 $dependency36,
    ) {}

    public function checksum(): int
    {
        return $this->dependency01->value()
            + $this->dependency02->value()
            + $this->dependency03->value()
            + $this->dependency04->value()
            + $this->dependency05->value()
            + $this->dependency06->value()
            + $this->dependency07->value()
            + $this->dependency08->value()
            + $this->dependency09->value()
            + $this->dependency10->value()
            + $this->dependency11->value()
            + $this->dependency12->value()
            + $this->dependency13->value()
            + $this->dependency14->value()
            + $this->dependency15->value()
            + $this->dependency16->value()
            + $this->dependency17->value()
            + $this->dependency18->value()
            + $this->dependency19->value()
            + $this->dependency20->value()
            + $this->dependency21->value()
            + $this->dependency22->value()
            + $this->dependency23->value()
            + $this->dependency24->value()
            + $this->dependency25->value()
            + $this->dependency26->value()
            + $this->dependency27->value()
            + $this->dependency28->value()
            + $this->dependency29->value()
            + $this->dependency30->value()
            + $this->dependency31->value()
            + $this->dependency32->value()
            + $this->dependency33->value()
            + $this->dependency34->value()
            + $this->dependency35->value()
            + $this->dependency36->value();
    }
}
