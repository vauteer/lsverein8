{!! $header !!}
{{-- pain.008.001.08 (DK Anlage 3, GBIC_4) loest pain.008.001.02 ab; ab 14.11.2026 nehmen
     die Banken nur noch diese Fassung an. Gegenueber .02 aendert sich am Aufbau nur
     der Namensraum und BIC heisst jetzt BICFI. --}}
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:pain.008.001.08" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:schemaLocation="urn:iso:std:iso:20022:tech:xsd:pain.008.001.08 pain.008.001.08.xsd">
    <CstmrDrctDbtInitn>
        <GrpHdr>
            <MsgId>{{ $msgId }}</MsgId>
            <CreDtTm>{{ $creDtTm }}</CreDtTm>
            <NbOfTxs>{{ $nbOfTxs }}</NbOfTxs>
            <CtrlSum>{{ $ctrlSum }}</CtrlSum>
            <InitgPty>
                <Nm>{!! $nm !!}</Nm>
            </InitgPty>
        </GrpHdr>
        <PmtInf>
            <PmtInfId>{{ $pmtInfId }}</PmtInfId>
            <PmtMtd>DD</PmtMtd>
            <BtchBookg>true</BtchBookg>
            <NbOfTxs>{{ $nbOfTxs }}</NbOfTxs>
            <CtrlSum>{{ $ctrlSum }}</CtrlSum>
            <PmtTpInf>
                <SvcLvl>
                    <Cd>SEPA</Cd>
                </SvcLvl>
                <LclInstrm>
                    <Cd>CORE</Cd>
                </LclInstrm>
                <SeqTp>RCUR</SeqTp>
            </PmtTpInf>
            <ReqdColltnDt>{{ $reqdColltnDt }}</ReqdColltnDt>
            <Cdtr>
                <Nm>{!! $nm !!}</Nm>
            </Cdtr>
            <CdtrAcct>
                <Id>
                    <IBAN>{{ $iban }}</IBAN>
                </Id>
            </CdtrAcct>
            <CdtrAgt>
                <FinInstnId>
                    <BICFI>{{ $bic }}</BICFI>
                </FinInstnId>
            </CdtrAgt>
            <ChrgBr>SLEV</ChrgBr>
            <CdtrSchmeId>
                <Id>
                    <PrvtId>
                        <Othr>
                            <Id>{{ $sepaId }}</Id>
                            <SchmeNm>
                                <Prtry>SEPA</Prtry>
                            </SchmeNm>
                        </Othr>
                    </PrvtId>
                </Id>
            </CdtrSchmeId>
            @foreach ($payments as $payment)
                <DrctDbtTxInf>
                    <PmtId>
                        <EndToEndId>NOTPROVIDED</EndToEndId>
                    </PmtId>
                    <InstdAmt Ccy="EUR">{{ $payment['instdAmt'] }}</InstdAmt>
                    <DrctDbtTx>
                        <MndtRltdInf>
                            <MndtId>{{ $payment['mndtId'] }}</MndtId>
                            <DtOfSgntr>{{ $payment['dtOfSgntr'] }}</DtOfSgntr>
                            <AmdmntInd>false</AmdmntInd>
                        </MndtRltdInf>
                    </DrctDbtTx>
                    <DbtrAgt>
                        <FinInstnId>
                            <BICFI>{{ $payment['bic'] }}</BICFI>
                        </FinInstnId>
                    </DbtrAgt>
                    <Dbtr>
                        <Nm>{!! $payment['nm'] !!}</Nm>
                    </Dbtr>
                    <DbtrAcct>
                        <Id>
                            <IBAN>{{ $payment['iban'] }}</IBAN>
                        </Id>
                    </DbtrAcct>
                    <RmtInf>
                        <Ustrd>{!! $payment['ustrd'] !!}</Ustrd>
                    </RmtInf>
                </DrctDbtTxInf>
            @endforeach
        </PmtInf>
    </CstmrDrctDbtInitn>
</Document>
