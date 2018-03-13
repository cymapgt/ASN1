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
use FreeDSx\Asn1\Exception\InvalidArgumentException;
use FreeDSx\Asn1\Exception\PartialPduException;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\BitStringType;
use FreeDSx\Asn1\Type\BooleanType;
use FreeDSx\Asn1\Type\EnumeratedType;
use FreeDSx\Asn1\Type\IncompleteType;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Asn1\Type\NullType;
use FreeDSx\Asn1\Type\OctetStringType;
use FreeDSx\Asn1\Type\SequenceType;
use FreeDSx\Asn1\Type\SetType;

/**
 * Basic Encoding Rules (BER) encoder.
 *
 * @author Chad Sikorra <Chad.Sikorra@gmail.com>
 */
class BerEncoder implements EncoderInterface
{
    /**
     * @var array
     */
    protected $appMap = [
        AbstractType::TAG_CLASS_APPLICATION => [
            0 => AbstractType::TAG_TYPE_SEQUENCE,
            1 => AbstractType::TAG_TYPE_SEQUENCE,
            2 => AbstractType::TAG_TYPE_NULL,
            3 => AbstractType::TAG_TYPE_SEQUENCE,
            4 => AbstractType::TAG_TYPE_SEQUENCE,
            5 => AbstractType::TAG_TYPE_SEQUENCE,
            6 => AbstractType::TAG_TYPE_SEQUENCE,
            7 => AbstractType::TAG_TYPE_SEQUENCE,
            8 => AbstractType::TAG_TYPE_SEQUENCE,
            9 => AbstractType::TAG_TYPE_SEQUENCE,
            10 => AbstractType::TAG_TYPE_OCTET_STRING,
            11 => AbstractType::TAG_TYPE_SEQUENCE,
            12 => AbstractType::TAG_TYPE_SEQUENCE,
            13 => AbstractType::TAG_TYPE_SEQUENCE,
            14 => AbstractType::TAG_TYPE_SEQUENCE,
            15 => AbstractType::TAG_TYPE_SEQUENCE,
            16 => AbstractType::TAG_TYPE_INTEGER,
            19 => AbstractType::TAG_TYPE_SEQUENCE,
            23 => AbstractType::TAG_TYPE_SEQUENCE,
            24 => AbstractType::TAG_TYPE_SEQUENCE,
            25 => AbstractType::TAG_TYPE_SEQUENCE,
        ],
        AbstractType::TAG_CLASS_CONTEXT_SPECIFIC => [],
        AbstractType::TAG_CLASS_PRIVATE => [],
    ];

    /**
     * @var array
     */
    protected $options = [
        'bitstring_padding' => '0',
    ];

    /**
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->options = array_merge($this->options, $options);
    }

    /**
     * {@inheritdoc}
     */
    public function decode($binary, array $tagMap = []) : AbstractType
    {
        if ($binary == '') {
            throw new InvalidArgumentException('The data to decode cannot be empty.');
        } elseif (strlen($binary) === 1) {
            throw new PartialPduException('Received only 1 byte of data.');
        }
        $info = $this->decodeBytes($binary, $tagMap, true);
        $info['type']->setTrailingData($info['bytes']);

        return $info['type'];
    }

    /**
     * {@inheritdoc}
     */
    public function complete(IncompleteType $type, int $tagType, array $tagMap = []) : AbstractType
    {
        return $this->getDecodedType($tagType, $type->getIsConstructed(), $type->getValue(), $tagMap)
            ->setTagNumber($type->getTagNumber())
            ->setTagClass($type->getTagClass());
    }

    /**
     * {@inheritdoc}
     */
    public function encode(AbstractType $type) : string
    {
        $tag = $type->getTagClass() | $type->getTagNumber() | ($type->getIsConstructed() ? AbstractType::CONSTRUCTED_TYPE : 0);
        $valueBytes = $this->getEncodedValue($type);
        $lengthBytes = $this->getEncodedLength(strlen($valueBytes));

        return chr($tag).$lengthBytes.$valueBytes;
    }

    /**
     * Map universal types to specific tag class values when decoding.
     *
     * @param int $class
     * @param array $map
     * @return $this
     */
    public function setTypeMap(int $class, array $map)
    {
        if (isset($this->appMap[$class])) {
            $this->appMap[$class] = $map;
        }

        return $this;
    }

    /**
     * Given a specific tag type / map, decode and construct the type.
     *
     * @param int|null $tagType
     * @param bool $isConstructed
     * @param string $bytes
     * @param array $tagMap
     * @return AbstractType
     * @throws EncoderException
     */
    protected function getDecodedType(?int $tagType, bool $isConstructed, $bytes, array $tagMap) : AbstractType
    {
        switch ($tagType) {
            case AbstractType::TAG_TYPE_BOOLEAN:
                $type = new BooleanType($this->decodeBoolean($bytes));
                break;
            case AbstractType::TAG_TYPE_NULL:
                $type = new NullType();
                break;
            case AbstractType::TAG_TYPE_INTEGER:
                $type = new IntegerType($this->decodeInteger($bytes));
                break;
            case AbstractType::TAG_TYPE_BIT_STRING:
                $type = new BitStringType($this->decodeBitString($bytes));
                break;
            case AbstractType::TAG_TYPE_ENUMERATED:
                $type = new EnumeratedType($this->decodeInteger($bytes));
                break;
            case AbstractType::TAG_TYPE_OCTET_STRING:
                $type = new OctetStringType($bytes);
                break;
            case AbstractType::TAG_TYPE_SEQUENCE:
                $type = new SequenceType(...$this->decodeConstructedType($bytes, $tagMap));
                break;
            case AbstractType::TAG_TYPE_SET:
                $type = new SetType(...$this->decodeConstructedType($bytes, $tagMap));
                break;
            case null:
                $type = new IncompleteType($bytes);
                break;
            default:
                throw new EncoderException(sprintf('Unable to decode value to a type for tag %s.', $tagType));
        }

        return $type;
    }

    /**
     * Get the encoded value for a specific type.
     *
     * @param AbstractType $type
     * @return string
     * @throws EncoderException
     */
    protected function getEncodedValue(AbstractType $type)
    {
        $bytes = null;

        switch ($type) {
            case $type instanceof BooleanType:
                $bytes = $this->encodeBoolean($type);
                break;
            case $type instanceof IntegerType:
            case $type instanceof EnumeratedType:
                $bytes = $this->encodeInteger($type);
                break;
            case $type instanceof OctetStringType:
                $bytes = $type->getValue();
                break;
            case $type->getIsConstructed():
                $bytes = $this->encodeConstructedType($type);
                break;
            case $type instanceof BitStringType:
                $bytes = $this->encodeBitString($type);
                break;
            case $type instanceof NullType:
                break;
            default:
                throw new EncoderException(sprintf('The type "%s" is not currently supported.', $type));
        }

        return $bytes;
    }

    /**
     * @param string $binary
     * @param array $tagMap
     * @param bool $isRoot
     * @return array
     * @throws EncoderException
     * @throws PartialPduException
     */
    protected function decodeBytes($binary, array $tagMap, bool $isRoot = false) : array
    {
        $data = ['type' => null, 'bytes' => null, 'trailing' => null];
        $tagMap = $tagMap + $this->appMap;

        $tag = $this->getDecodedTag(ord($binary[0]));
        $length = $this->getDecodedLength(substr($binary, 1));
        $tagType = $this->getTagType($tag['number'], $tag['class'], $tagMap);

        $totalLength = 1 + $length['length_length'] + $length['value_length'];
        if (strlen($binary) < $totalLength) {
            $message = sprintf(
                'The expected byte length was %s, but received %s.',
                $totalLength,
                strlen($binary)
            );
            if ($isRoot) {
                throw new PartialPduException($message);
            } else {
                throw new EncoderException($message);
            }
        }

        $data['type'] = $this->getDecodedType($tagType, $tag['constructed'], substr($binary, 1 + $length['length_length'], $length['value_length']), $tagMap);
        $data['type']->setTagClass($tag['class']);
        $data['type']->setTagNumber($tag['number']);
        $data['type']->setIsConstructed($tag['constructed']);
        $data['bytes'] = substr($binary, $totalLength);

        return $data;
    }

    /**
     * From a specific tag number and class try to determine what universal ASN1 type it should be mapped to. If there
     * is no mapping defined it will return null. In this case the binary data will be wrapped into an IncompleteType.
     *
     * @param int $tagNumber
     * @param int $tagClass
     * @param array $map
     * @return int|null
     */
    protected function getTagType(int $tagNumber, int $tagClass, array $map) : ?int
    {
        if ($tagClass === AbstractType::TAG_CLASS_UNIVERSAL) {
            return $tagNumber;
        }

        return $map[$tagClass][$tagNumber] ?? null;
    }

    /**
     * @param string $bytes
     * @return array
     * @throws EncoderException
     */
    protected function getDecodedLength($bytes) : array
    {
        $info = ['value_length' => isset($bytes[0]) ? ord($bytes[0]) : 0, 'length_length' => 1];

        # Restricted per the LDAP RFC 4511 section 5.1
        if ($info['value_length'] === 128) {
            throw new EncoderException('Indefinite length encoding is not supported.');
        }

        # Long definite length has a special encoding.
        if ($info['value_length'] > 127) {
            # The length of the length bytes is in the first 7 bits. So remove the MSB to get the value.
            $info['length_length'] = $info['value_length'] & ~0x80;

            # The value of 127 is marked as reserved in the spec
            if ($info['length_length'] === 127) {
                throw new EncoderException('The decoded length cannot be equal to 127 bytes.');
            }
            if ($info['length_length'] + 1 > strlen($bytes)) {
                throw new PartialPduException('Not enough data to decode the length.');
            }

            # Base 256 encoded
            $info['value_length'] = 0;
            for ($i = 1; $i < $info['length_length'] + 1; $i++) {
                $info['value_length'] = $info['value_length'] * 256 + ord($bytes[$i]);
            }

            # Add the byte that represents the length of the length
            $info['length_length']++;
        }

        return $info;
    }

    /**
     * @param $tag
     * @return array
     */
    protected function getDecodedTag(int $tag) : array
    {
        $info = ['class' => null, 'number' => null, 'constructed' => null];

        if ($tag & AbstractType::TAG_CLASS_APPLICATION && $tag & AbstractType::TAG_CLASS_CONTEXT_SPECIFIC) {
            $info['class'] = AbstractType::TAG_CLASS_PRIVATE;
        } elseif ($tag & AbstractType::TAG_CLASS_APPLICATION) {
            $info['class'] = AbstractType::TAG_CLASS_APPLICATION;
        } elseif ($tag & AbstractType::TAG_CLASS_CONTEXT_SPECIFIC) {
            $info['class'] = AbstractType::TAG_CLASS_CONTEXT_SPECIFIC;
        } else {
            $info['class'] = AbstractType::TAG_CLASS_UNIVERSAL;
        }
        $info['constructed'] = (bool) ($tag & AbstractType::CONSTRUCTED_TYPE);
        $info['number'] = bindec((substr(decbin($tag), -5)));

        return $info;
    }

    /**
     * @param int $num
     * @return string
     * @throws EncoderException
     */
    protected function getEncodedLength(int $num)
    {
        # Short definite length, nothing to do
        if ($num < 128) {
            return chr($num);
        } else {
            return $this->encodeLongDefiniteLength($num);
        }
    }

    /**
     * @param int $num
     * @return string
     * @throws EncoderException
     */
    protected function encodeLongDefiniteLength(int $num)
    {
        # Long definite length is base 256 encoded. This seems kinda inefficient. Found on base_convert comments.
        $num = base_convert($num, 10, 2);
        $num = str_pad($num, ceil(strlen($num) / 8) * 8, '0', STR_PAD_LEFT);

        $bytes = '';
        for ($i = strlen($num) - 8; $i >= 0; $i -= 8) {
            $bytes = chr(base_convert(substr($num, $i, 8), 2, 10)).$bytes;
        }

        $length = strlen($bytes);
        if ($length >= 127) {
            throw new EncoderException('The encoded length cannot be greater than or equal to 127 bytes');
        }

        return chr(0x80 | $length).$bytes;
    }

    /**
     * @param $num
     * @return string
     */
    protected function packNumber($num)
    {
        # 8bit
        if ($num <= 255) {
            $size = 'C';
        # 16bit
        } elseif ($num <= 32767) {
            $size = 'n';
        # 32bit
        } else {
            $size = 'N';
        }

        return pack($size, $num);
    }

    /**
     * @param BooleanType $type
     * @return string
     */
    protected function encodeBoolean(BooleanType $type)
    {
        return chr($type->getValue() ? 0xFF : 0x00);
    }

    /**
     * @todo
     * @param BitStringType $type
     * @return string
     */
    protected function encodeBitString(BitStringType $type)
    {
        $length = strlen($type->getValue());
        if ($length % 8 === 0) {
            $data = $type->getValue();
            $unused = 0;
            for($i = $length -1; $i > 0; $i--) {
                if ($data[$i] === '0') {
                    $unused++;
                } else {
                    break;
                }
            }
        } else {
            $unused = 8 - ($length % 8);
            $data = str_pad($type->getValue(), $length + $unused, '0');
        }

        $bytes = chr($unused);
        for ($i = 0; $i < strlen($data) / 8; $i++) {
            $bytes .= chr(bindec(substr($data, $i * 8, 8)));
        }

        return $bytes;
    }

    /**
     * Kinda ugly, but the LDAP max int is 32bit.
     *
     * @param AbstractType $type
     * @return string
     */
    protected function encodeInteger(AbstractType $type) : string
    {
        $int = abs($type->getValue());
        $isNegative = ($type->getValue() < 0);

        # @todo Shouldn't have to do this...the logic is wrong somewhere below.
        if ($isNegative && $int === 128) {
            return chr(0x80);
        }

        # Subtract one for Two's Complement...
        if ($isNegative) {
            $int = $int - 1;
        }
        $bytes = $this->packNumber($int);

        # Two's Complement, invert the bits...
        if ($isNegative) {
            $len = strlen($bytes);
            for ($i = 0; $i < $len; $i++) {
                $bytes[$i] = ~$bytes[$i];
            }
        }

        # MSB == Most Significant Bit. The one used for the sign.
        $msbSet = (bool) (ord($bytes[0]) & 0x80);
        if (!$isNegative && $msbSet) {
            $bytes = "\x00".$bytes;
        } elseif (($isNegative && !$msbSet) || ($isNegative && ($int <= 127))) {
            $bytes = "\xFF".$bytes;
        }

        return $bytes;
    }

    /**
     * @param $bytes
     * @return bool
     */
    protected function decodeBoolean($bytes) : bool
    {
        return ord($bytes[0]) !== 0;
    }

    /**
     * @param $bytes
     * @return string
     */
    protected function decodeBitString($bytes) : string
    {
        # The first byte represents the number of unused bits at the end.
        $unused = ord($bytes[0]);
        $bytes = substr($bytes, 1);
        $length = strlen($bytes);

        $bitstring = '';
        for ($i = 0; $i < $length; $i++) {
            $octet = sprintf( "%08d", decbin(ord($bytes[$i])));
            if ($i === ($length - 1) && $unused) {
                $bitstring .= substr($octet, 0, ($unused * -1));
            } else {
                $bitstring .= $octet;
            }
        }

        return $bitstring;
    }

    /**
     * @param string $bytes
     * @return int number
     */
    protected function decodeInteger($bytes) : int
    {
        $isNegative = (ord($bytes[0]) & 0x80);
        $len = strlen($bytes);

        # Cheat a bit...max int in LDAP is 32-bit
        if ($len <= 1) {
            $size = 'C';
        } elseif ($len <= 2) {
            $size = 'n';
        } else {
            $size = 'N';
        }

        # Need to reverse Two's Complement. Invert the bits...
        if ($isNegative) {
            for ($i = 0; $i < $len; $i++) {
                $bytes[$i] = ~$bytes[$i];
            }
        }
        $int = unpack($size."1int", $bytes)['int'];

        # Complete Two's Complement by adding 1 and turning it negative...
        if ($isNegative) {
            $int = ($int + 1) * -1;
        }

        return $int;
    }

    /**
     * @param AbstractType $type
     * @return string
     */
    protected function encodeConstructedType(AbstractType $type) : string
    {
        $bytes = '';

        foreach ($type->getChildren() as $child) {
            $bytes .= $this->encode($child);
        }

        return $bytes;
    }

    /**
     * @param string $bytes
     * @param array $tagMap
     * @return array
     * @throws EncoderException
     * @throws PartialPduException
     */
    protected function decodeConstructedType($bytes, array $tagMap)
    {
        $children = [];

        while ($bytes) {
            list('type' => $type, 'bytes' => $bytes) = $this->decodeBytes($bytes, $tagMap);
            $children[] = $type;
        }

        return $children;
    }
}
