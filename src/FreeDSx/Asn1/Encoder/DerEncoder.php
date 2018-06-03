<?php
/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace FreeDSx\Asn1\Encoder;

use FreeDSx\Asn1\Exception\EncoderException;
use FreeDSx\Asn1\Type\AbstractStringType;
use FreeDSx\Asn1\Type\AbstractTimeType;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\BitStringType;
use FreeDSx\Asn1\Type\OctetStringType;
use FreeDSx\Asn1\Type\SetTrait;
use FreeDSx\Asn1\Type\SetType;

/**
 * Distinguished Encoding Rules (DER) encoder.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class DerEncoder extends BerEncoder
{
    use CerDerTrait,
        SetTrait;

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        parent::__construct($options);
        $this->setOptions([
            'bitstring_padding' => '0',
            'primitive_only' => [
                AbstractType::TAG_TYPE_NUMERIC_STRING,
                AbstractType::TAG_TYPE_PRINTABLE_STRING,
                AbstractType::TAG_TYPE_TELETEX_STRING,
                AbstractType::TAG_TYPE_VIDEOTEX_STRING,
                AbstractType::TAG_TYPE_IA5_STRING,
                AbstractType::TAG_TYPE_GRAPHIC_STRING,
                AbstractType::TAG_TYPE_VISIBLE_STRING,
                AbstractType::TAG_TYPE_GENERAL_STRING,
                AbstractType::TAG_TYPE_BMP_STRING,
                AbstractType::TAG_TYPE_UNIVERSAL_STRING,
                AbstractType::TAG_TYPE_UTF8_STRING,
                AbstractType::TAG_TYPE_BIT_STRING,
                AbstractType::TAG_TYPE_OCTET_STRING,
            ]
        ]);
    }

    /**
     * {@inheritdoc}
     */
    protected function getEncodedValue(AbstractType $type)
    {
        $this->validate($type);

        return parent::getEncodedValue($type);
    }

    /**
     *{@inheritdoc}
     */
    protected function getDecodedType(int $tagType = null, bool $isConstructed, $bytes, array $tagMap) : AbstractType
    {
        $type = parent::getDecodedType($tagType, $isConstructed, $bytes, $tagMap);
        $this->validate($type);

        return $type;
    }

    /**
     * {@inheritdoc}
     */
    protected function decodeLongDefiniteLength($bytes, array $info): array
    {
        $info = parent::decodeLongDefiniteLength($bytes, $info);

        if ($info['value_length'] < 127) {
            throw new EncoderException('DER must be encoded using the shortest possible length form, but it is not.');
        }

        return $info;
    }

    /**
     * {@inheritdoc}
     * @throws EncoderException
     */
    protected function encodeSet(SetType $set)
    {
        return $this->encodeConstructedType(...$this->canonicalize(...$set->getChildren()));
    }

    /**
     * @param AbstractType $type
     * @throws EncoderException
     */
    protected function validate(AbstractType $type)
    {
        if ($type instanceof OctetStringType && $type->getIsConstructed()) {
            throw new EncoderException('The octet string must be primitive. It cannot be constructed.');
        }
        if ($type instanceof BitStringType && $type->getIsConstructed()) {
            throw new EncoderException('The bit string must be primitive. It cannot be constructed.');
        }
        if ($type instanceof AbstractStringType && $type->isCharacterRestricted() && $type->getIsConstructed()) {
            throw new EncoderException('Character restricted string types must be primitive.');
        }
        if ($type instanceof AbstractTimeType) {
            $this->validateTimeType($type);
        }
    }
    
    /**
     * @param AbstractTimeType $type
     * @throws EncoderException
     */
    protected function validateTimeType(AbstractTimeType $type)
    {
        if ($type->getTimeZoneFormat() !== AbstractTimeType::TZ_UTC) {
            throw new EncoderException(sprintf(
                'Time must end in a Z, but it does not. It is set to "%s".',
                $type->getTimeZoneFormat()
            ));
        }
        $dtFormat = $type->getDateTimeFormat();
        if (!($dtFormat === AbstractTimeType::FORMAT_SECONDS || $dtFormat === AbstractTimeType::FORMAT_FRACTIONS)) {
            throw new EncoderException(sprintf(
                'Time must be specified to the seconds, but it is specified to "%s".',
                $dtFormat
            ));
        }
    } 
    
    /**
     * X.680 Sec 8.4. A set is canonical when:
     *    - Universal classes first.
     *    - Application classes second.
     *    - Context specific classes third.
     *    - Private classes last.
     *    - Within each group of classes above, tag numbers should be ordered in ascending order.
     *
     * @param AbstractType[] ...$set
     * @return AbstractType[]
     */
    protected function canonicalize(AbstractType ...$set) : array
    {
        $children = [
            AbstractType::TAG_CLASS_UNIVERSAL => [],
            AbstractType::TAG_CLASS_APPLICATION => [],
            AbstractType::TAG_CLASS_CONTEXT_SPECIFIC => [],
            AbstractType::TAG_CLASS_PRIVATE => [],
        ];

        # Group them by their respective class type.
        foreach ($set as $child) {
            $children[$child->getTagClass()][] = $child;
        }

        # Sort the classes by tag number.
        foreach ($children as $class => $type) {
            usort($children[$class], function ($a, $b) {
                /* @var AbstractType $a
                 * @var AbstractType $b */
                return ($a->getTagNumber() < $b->getTagNumber()) ? -1 : 1;
            });
        }

        return array_merge(
            $children[AbstractType::TAG_CLASS_UNIVERSAL],
            $children[AbstractType::TAG_CLASS_APPLICATION],
            $children[AbstractType::TAG_CLASS_CONTEXT_SPECIFIC],
            $children[AbstractType::TAG_CLASS_PRIVATE]
        );
    }
    
    /**
     * @param string $bytes
     * @param int $length
     * @param int $unused
     * @return string
     * @throws EncoderException
     */
    protected function binaryToBitString($bytes, int $length, int $unused) : string
    {
        $bytesOffsetNegativeByOne = substr($bytes, strlen($bytes) - 1);

        //if ($unused && $length && ord($bytes[-1]) !== 0 && ((8 - $length) << ord($bytes[-1])) !== 0) {  Will not work in PHP  <= 7.0
        if ($unused && $length && ord($bytesOffsetNegativeByOne) !== 0 && ((8 - $length) << ord($bytesOffsetNegativeByOne)) !== 0) {
            throw new EncoderException(sprintf(
                'The last %s unused bits of the bit string must be 0, but they are not.',
                $unused
            ));
        }

        return parent::binaryToBitString($bytes, $length, $unused);
    }    
}
