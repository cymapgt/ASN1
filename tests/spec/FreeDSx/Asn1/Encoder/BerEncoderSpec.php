<?php
/**
 * This file is part of the FreeDSx ASN1 package.
 *
 * (c) Chad Sikorra <Chad.Sikorra@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace spec\FreeDSx\Asn1\Encoder;

use FreeDSx\Asn1\Encoder\BerEncoder;
use FreeDSx\Asn1\Type\AbstractType;
use FreeDSx\Asn1\Type\BitStringType;
use FreeDSx\Asn1\Type\BmpStringType;
use FreeDSx\Asn1\Type\BooleanType;
use FreeDSx\Asn1\Type\CharacterStringType;
use FreeDSx\Asn1\Type\EnumeratedType;
use FreeDSx\Asn1\Type\GeneralizedTimeType;
use FreeDSx\Asn1\Type\GeneralStringType;
use FreeDSx\Asn1\Type\GraphicStringType;
use FreeDSx\Asn1\Type\IA5StringType;
use FreeDSx\Asn1\Type\IncompleteType;
use FreeDSx\Asn1\Type\IntegerType;
use FreeDSx\Asn1\Type\NullType;
use FreeDSx\Asn1\Type\NumericStringType;
use FreeDSx\Asn1\Type\OctetStringType;
use FreeDSx\Asn1\Type\OidType;
use FreeDSx\Asn1\Type\PrintableStringType;
use FreeDSx\Asn1\Type\RelativeOidType;
use FreeDSx\Asn1\Type\SequenceType;
use FreeDSx\Asn1\Exception\EncoderException;
use FreeDSx\Asn1\Exception\PartialPduException;
use FreeDSx\Asn1\Type\TeletexStringType;
use FreeDSx\Asn1\Type\UniversalStringType;
use FreeDSx\Asn1\Type\UtcTimeType;
use FreeDSx\Asn1\Type\Utf8StringType;
use FreeDSx\Asn1\Type\VideotexStringType;
use FreeDSx\Asn1\Type\VisibleStringType;
use PhpSpec\ObjectBehavior;

class BerEncoderSpec extends ObjectBehavior
{
    function it_is_initializable()
    {
        $this->shouldHaveType(BerEncoder::class);
    }

    function it_should_implement_the_encoder_interface()
    {
        $this->shouldImplement('FreeDSx\Asn1\Encoder\EncoderInterface');
    }

    function it_should_decode_long_definite_length()
    {
        $chars = str_pad('0', 131071, '0');

        $this->decode(hex2bin('048301ffff').$chars)->shouldBeLike(new OctetStringType($chars));
    }

    function it_should_encode_long_definite_length()
    {
        $chars = str_pad('', 131071, '0');

        $this->encode(new OctetStringType($chars))->shouldBeEqualTo(hex2bin('048301ffff').$chars);
    }

    function it_should_not_allow_long_definite_length_greater_than_or_equal_to_127()
    {
        $this->shouldThrow(EncoderException::class)->duringDecode(hex2bin('04ff'));
    }

    function it_should_decode_a_boolean_true_type()
    {
        $this->decode(hex2bin('0101FF'))->shouldBeLike(new BooleanType(true));
        $this->decode(hex2bin('0101F3'))->shouldBeLike(new BooleanType(true));
    }

    function it_should_decode_a_boolean_false_type()
    {
        $this->decode(hex2bin('010100'))->shouldBeLike(new BooleanType(false));
    }

    function it_should_encode_a_boolean_type()
    {
        $this->encode(new BooleanType(true))->shouldBeEqualTo(hex2bin('0101FF'));
        $this->encode(new BooleanType(false))->shouldBeEqualTo(hex2bin('010100'));
    }

    function it_should_decode_a_null_type()
    {
        $this->decode(hex2bin('0500'))->shouldBeLike(new NullType());
    }

    function it_should_encode_a_null_type()
    {
        $this->encode(new NullType())->shouldBeEqualTo(hex2bin('0500'));
    }

    function it_should_decode_a_zero_integer_type()
    {
        $this->decode(hex2bin('020100'))->shouldBeLike(new IntegerType(0));
    }

    function it_should_encode_a_zero_integer_type()
    {
        $this->encode(new IntegerType(0))->shouldBeEqualTo(hex2bin('020100'));
    }

    function it_should_decode_a_positive_integer_type()
    {
        $this->decode(hex2bin('020269BA'))->shouldBeLike(new IntegerType(27066));
        $this->decode(hex2bin('02020100'))->shouldBeLike(new IntegerType(256));
        $this->decode(hex2bin('020200FF'))->shouldBeLike(new IntegerType(255));
        $this->decode(hex2bin('02017F'))->shouldBeLike(new IntegerType(127));
        $this->decode(hex2bin('02020080'))->shouldBeLike(new IntegerType(128));
    }

    function it_should_encode_a_positive_integer_type()
    {
        $this->encode(new IntegerType(27066))->shouldBeEqualTo(hex2bin('020269BA'));
        $this->encode(new IntegerType(256))->shouldBeEqualTo(hex2bin('02020100'));
        $this->encode(new IntegerType(255))->shouldBeEqualTo(hex2bin('020200FF'));
        $this->encode(new IntegerType(127))->shouldBeEqualTo(hex2bin('02017F'));
        $this->encode(new IntegerType(128))->shouldBeEqualTo(hex2bin('02020080'));
    }

    function it_should_decode_a_negative_integer_type()
    {
        $this->decode(hex2bin('02029646'))->shouldBeLike(new IntegerType(-27066));
        $this->decode(hex2bin('0202FF81'))->shouldBeLike(new IntegerType(-127));
        $this->decode(hex2bin('020180'))->shouldBeLike(new IntegerType(-128));
        $this->decode(hex2bin('0202FF7F'))->shouldBeLike(new IntegerType(-129));
        $this->decode(hex2bin('0202FFFF'))->shouldBeLike(new IntegerType(-1));
    }

    function it_should_encode_a_negative_integer_type()
    {
        $this->encode(new IntegerType(-27066))->shouldBeEqualTo(hex2bin('02029646'));
        $this->encode(new IntegerType(-127))->shouldBeEqualTo(hex2bin('0202FF81'));
        $this->encode(new IntegerType(-128))->shouldBeEqualTo(hex2bin('020180'));
        $this->encode(new IntegerType(-129))->shouldBeEqualTo(hex2bin('0202FF7F'));
        $this->encode(new IntegerType(-1))->shouldBeEqualTo(hex2bin('0202FFFF'));
    }

    function it_should_decode_an_octet_string_type()
    {
        $this->decode(hex2bin('0416312e332e362e312e342e312e313436362e3230303337'))->shouldBeLike(new OctetStringType('1.3.6.1.4.1.1466.20037'));
    }

    function it_should_encode_an_octet_string()
    {
        $this->encode(new OctetStringType('1.3.6.1.4.1.1466.20037'))->shouldBeEqualTo(hex2bin('0416312e332e362e312e342e312e313436362e3230303337'));
    }

    function it_should_decode_an_enumerated_type()
    {
        $this->decode(hex2bin('0A0101'))->shouldBeLike(new EnumeratedType(1));
    }

    function it_should_encode_an_enumerated_type()
    {
        $this->encode(new EnumeratedType(1))->shouldBeEqualTo(hex2bin('0A0101'));
    }

    function it_should_decode_a_sequence_type()
    {
        $this->decode(hex2bin('30090201010201020101ff'))->shouldBeLike(new SequenceType(
            new IntegerType(1),
            new IntegerType(2),
            new BooleanType(true)
        ));
    }

    function it_should_encode_a_sequence_type()
    {
        $this->encode(new SequenceType(
            new IntegerType(1),
            new IntegerType(2),
            new BooleanType(true)
        ))->shouldBeEqualTo(hex2bin('30090201010201020101ff'));
    }

    function it_should_encode_a_bit_string()
    {
        $this->encode(new BitStringType('011011100101110111'))->shouldBeEqualTo(hex2bin('0304066e5dc0'));
        $this->encode(new BitStringType('11111111'))->shouldBeEqualTo(hex2bin('030200ff'));
        $this->encode(new BitStringType('0'))->shouldBeEqualTo(hex2bin('03020700'));
        $this->encode(new BitStringType(''))->shouldBeEqualTo(hex2bin('030100'));
    }

    function it_should_decode_a_bit_string()
    {
        $this->decode(hex2bin('0304066e5dc0'))->shouldBeLike(new BitStringType('011011100101110111'));
        $this->decode(hex2bin('030200ff'))->shouldBeLike(new BitStringType('11111111'));
        $this->decode(hex2bin('03020700'))->shouldBeLike(new BitStringType('0'));
        $this->decode(hex2bin('030100'))->shouldBeLike(new BitStringType(''));
    }

    function it_should_decode_an_oid()
    {
        $this->decode(hex2bin('06092b0601040182371514'))->shouldBeLike(new OidType('1.3.6.1.4.1.311.21.20'));
        $this->decode(hex2bin('06062a864886f70d'))->shouldBeLike(new OidType('1.2.840.113549'));
        $this->decode(hex2bin('06022a7f'))->shouldBeLike(new OidType('1.2.127'));
        $this->decode(hex2bin('06032a8100'))->shouldBeLike(new OidType('1.2.128'));
        $this->decode(hex2bin('06032ac000'))->shouldBeLike(new OidType('1.2.8192'));
        $this->decode(hex2bin('06032aff7f'))->shouldBeLike(new OidType('1.2.16383'));
        $this->decode(hex2bin('06052a81808000'))->shouldBeLike(new OidType('1.2.2097152'));
        $this->decode(hex2bin('06052affffff7f'))->shouldBeLike(new OidType('1.2.268435455'));
    }

    function it_should_encode_an_oid()
    {
        $this->encode(new OidType('1.3.6.1.4.1.311.21.20'))->shouldBeEqualTo(hex2bin('06092b0601040182371514'));
        $this->encode(new OidType('1.2.840.113549'))->shouldBeEqualTo(hex2bin('06062a864886f70d'));
        $this->encode(new OidType('1.2.127'))->shouldBeEqualTo(hex2bin('06022a7f'));
        $this->encode(new OidType('1.2.128'))->shouldBeEqualTo(hex2bin('06032a8100'));
        $this->encode(new OidType('1.2.8192'))->shouldBeEqualTo(hex2bin('06032ac000'));
        $this->encode(new OidType('1.2.16383'))->shouldBeEqualTo(hex2bin('06032aff7f'));
        $this->encode(new OidType('1.2.2097152'))->shouldBeEqualTo(hex2bin('06052a81808000'));
        $this->encode(new OidType('1.2.268435455'))->shouldBeEqualTo(hex2bin('06052affffff7f'));
    }

    function it_should_encode_a_generalized_time_string_non_utc_with_a_differential()
    {
        $datetime = new \DateTime('20180318', new \DateTimeZone('America/Chicago'));
        $this->encode(new GeneralizedTimeType($datetime, GeneralizedTimeType::FORMAT_SECONDS, GeneralizedTimeType::TZ_DIFF))->shouldBeEqualTo(hex2bin('1813').'20180318000000-0500');
    }

    function it_should_encode_a_generalized_time_string_utc_with_an_ending_Z()
    {
        $datetime = new \DateTime('20180318', new \DateTimeZone('UTC'));
        $this->encode(new GeneralizedTimeType($datetime, GeneralizedTimeType::FORMAT_SECONDS, GeneralizedTimeType::TZ_UTC))->shouldBeEqualTo(hex2bin('180f').'20180318000000Z');
    }

    function it_should_encode_a_generalized_time_string_as_local_time_if_specified()
    {
        $time = new GeneralizedTimeType(
            new \DateTime('20180318', new \DateTimeZone(date_default_timezone_get())),
            GeneralizedTimeType::FORMAT_SECONDS,
            GeneralizedTimeType::TZ_LOCAL
        );
        $this->encode($time)->shouldBeEqualTo(hex2bin('180e').'20180318000000');
    }

    function it_should_encode_a_generalized_time_with_fractional_seconds_if_they_exist()
    {
        $this->encode(new GeneralizedTimeType(new \DateTime('2018-03-18T10:02:01.012300Z')))->shouldBeEqualTo(hex2bin('1814').'20180318100201.0123Z');
    }

    function it_should_not_encode_a_generalized_time_with_fractional_seconds_if_specified()
    {
        $datetime = new GeneralizedTimeType(new \DateTime('2018-03-18T10:02:01.0123Z'), GeneralizedTimeType::FORMAT_SECONDS);
        $this->encode($datetime)->shouldBeEqualTo(hex2bin('180f').'20180318100201Z');
    }

    function it_should_encode_a_generalized_time_string_to_hours()
    {
        $this->encode(new GeneralizedTimeType(
            new \DateTime('2018-03-18 01:00', new \DateTimeZone(date_default_timezone_get())),
            GeneralizedTimeType::FORMAT_HOURS,
            GeneralizedTimeType::TZ_LOCAL
        ))->shouldBeEqualTo(hex2bin('180a').'2018031801');
    }

    function it_should_encode_a_generalized_time_string_to_minutes()
    {
        $this->encode(new GeneralizedTimeType(
            new \DateTime('2018-03-18 01:22', new \DateTimeZone('UTC')),
            GeneralizedTimeType::FORMAT_MINUTES,
            GeneralizedTimeType::TZ_UTC
        ))->shouldBeEqualTo(hex2bin('180d').'201803180122Z');
    }

    function it_should_throw_an_exception_if_the_hour_is_equal_to_24_when_decoding()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('180b').'2018031824Z']);
    }

    function it_should_decode_a_generalized_time_string_with_hours_in_UTC()
    {
        $this->decode(hex2bin('180b').'2018031801Z')->shouldBeLike(new GeneralizedTimeType(
            new \DateTime('2018-03-18 01:00', new \DateTimeZone('UTC')),
            GeneralizedTimeType::FORMAT_HOURS,
            GeneralizedTimeType::TZ_UTC
        ));
    }

    function it_should_decode_a_generalized_time_string_in_local_time_form()
    {
        $this->decode(hex2bin('180a').'2018031801')->shouldBeLike(new GeneralizedTimeType(
            new \DateTime('2018-03-18 01:00', new \DateTimeZone(date_default_timezone_get())),
            GeneralizedTimeType::FORMAT_HOURS,
            GeneralizedTimeType::TZ_LOCAL
        ));
    }

    function it_should_decode_a_generalized_time_string_with_minutes()
    {
        $this->decode(hex2bin('180d').'201803180122Z')->shouldBeLike(new GeneralizedTimeType(
            new \DateTime('2018-03-18 01:22', new \DateTimeZone('UTC')),
            GeneralizedTimeType::FORMAT_MINUTES,
            GeneralizedTimeType::TZ_UTC
        ));
    }

    function it_should_decode_a_generalized_time_string_with_seconds()
    {
        $this->decode(hex2bin('180f').'20180318012241Z')->shouldBeLike(new GeneralizedTimeType(
            new \DateTime('2018-03-18 01:22:41', new \DateTimeZone('UTC')),
            GeneralizedTimeType::FORMAT_SECONDS,
            GeneralizedTimeType::TZ_UTC
        ));
    }

    function it_should_decode_a_generalized_time_string_with_fractions_of_a_second()
    {
        $this->decode(hex2bin('1811').'19851106210627.3Z')->shouldBeLike(new GeneralizedTimeType(
            new \DateTime('1985-11-06 21:06:27.3', new \DateTimeZone('UTC')),
            GeneralizedTimeType::FORMAT_FRACTIONS,
            GeneralizedTimeType::TZ_UTC
        ));
    }

    function it_should_decode_a_generalized_time_string_with_a_time_differential()
    {
        $this->decode(hex2bin('1815').'19851106210627.3-0500')->shouldBeLike(new GeneralizedTimeType(
            new \DateTime('1985-11-06 21:06:27.3', new \DateTimeZone('-0500')),
            GeneralizedTimeType::FORMAT_FRACTIONS,
            GeneralizedTimeType::TZ_DIFF
        ));
    }

    function it_should_decode_a_UTC_time_with_seconds()
    {
        $this->decode(hex2bin('170d').'180318012241Z')->shouldBeLike(new UtcTimeType(
            new \DateTime('18-03-18 01:22:41', new \DateTimeZone('UTC')),
            GeneralizedTimeType::FORMAT_SECONDS,
            GeneralizedTimeType::TZ_UTC
        ));
    }

    function it_should_decode_a_UTC_time_without_seconds()
    {
        $this->decode(hex2bin('170b').'1803180122Z')->shouldBeLike(new UtcTimeType(
            new \DateTime('18-03-18 01:22', new \DateTimeZone('UTC')),
            GeneralizedTimeType::FORMAT_MINUTES,
            GeneralizedTimeType::TZ_UTC
        ));
    }

    function it_should_decode_a_UTC_time_with_a_differential_timezone()
    {
        $this->decode(hex2bin('1711').'181106210627-0500')->shouldBeLike(new UtcTimeType(
            new \DateTime('18-11-06 21:06:27', new \DateTimeZone('-0500')),
            GeneralizedTimeType::FORMAT_SECONDS,
            GeneralizedTimeType::TZ_DIFF
        ));
    }

    function it_should_not_accept_decoding_UTC_time_with_no_timezone_modifier()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [(hex2bin('170c').'181106210627')]);
    }

    function it_should_not_accept_decoding_UTC_time_with_24_hour_midnight()
    {
        $this->shouldThrow(new EncoderException('Midnight must only be specified by 00, but got 24.'))->during('decode', [(hex2bin('170d').'181106240627Z')]);
    }

    function it_should_encode_a_UTC_time_with_seconds()
    {
        $this->encode(new UtcTimeType(
            new \DateTime('18-03-18 01:22:41', new \DateTimeZone('UTC')),
            GeneralizedTimeType::FORMAT_SECONDS,
            GeneralizedTimeType::TZ_UTC
        ))->shouldBeEqualTo(hex2bin('170d').'180318012241Z');
    }

    function it_should_encode_a_UTC_time_without_seconds()
    {
        $this->encode(new UtcTimeType(
            new \DateTime('18-03-18 01:22', new \DateTimeZone('UTC')),
            GeneralizedTimeType::FORMAT_MINUTES,
            GeneralizedTimeType::TZ_UTC
        ))->shouldBeEqualTo(hex2bin('170b').'1803180122Z');
    }

    function it_should_encode_a_UTC_time_with_a_differential_timezone()
    {
        $this->encode(new UtcTimeType(
            new \DateTime('18-11-06 21:06:27', new \DateTimeZone('-0500')),
            GeneralizedTimeType::FORMAT_SECONDS,
            GeneralizedTimeType::TZ_DIFF
        ))->shouldBeEqualTo(hex2bin('1711').'181106210627-0500');
    }

    function it_should_encode_a_bmp_string()
    {
        $this->encode(new BmpStringType('foo'))->shouldBeEqualTo(hex2bin('1e03').'foo');
    }

    function it_should_decode_a_bmp_string()
    {
        $this->decode(hex2bin('1e03').'foo')->shouldBeLike(new BmpStringType('foo'));
    }

    function it_should_encode_a_character_string()
    {
        $this->encode(new CharacterStringType('foo'))->shouldBeEqualTo(hex2bin('1d03').'foo');
    }

    function it_should_decode_a_character_string()
    {
        $this->decode(hex2bin('1d03').'foo')->shouldBeLike(new CharacterStringType('foo'));
    }

    function it_should_encode_a_general_string()
    {
        $this->encode(new GeneralStringType('foo'))->shouldBeLike(hex2bin('1b03').'foo');
    }

    function it_should_decode_a_general_string()
    {
        $this->decode(hex2bin('1b03').'foo')->shouldBeLike(new GeneralStringType('foo'));
    }

    function it_should_encode_a_graphic_string()
    {
        $this->encode(new GraphicStringType('foo'))->shouldBeLike(hex2bin('1903').'foo');
    }

    function it_should_decode_a_graphic_string()
    {
        $this->decode(hex2bin('1903').'foo')->shouldBeLike(new GraphicStringType('foo'));
    }

    function it_should_encode_an_ia5_string()
    {
        $this->encode(new IA5StringType('foo'))->shouldBeLike(hex2bin('1603').'foo');
    }

    function it_should_decode_an_ia5_string()
    {
        $this->decode(hex2bin('1603').'foo')->shouldBeLike(new IA5StringType('foo'));
    }

    function it_should_encode_a_numeric_string()
    {
        $this->encode(new NumericStringType('123'))->shouldBeLike(hex2bin('1203').'123');
    }

    function it_should_decode_a_numeric_string()
    {
        $this->decode(hex2bin('1203').'123')->shouldBeLike(new NumericStringType('123'));
    }

    function it_should_encode_a_printable_string()
    {
        $this->encode(new PrintableStringType('foo'))->shouldBeLike(hex2bin('1303').'foo');
    }

    function it_should_decode_a_printable_string()
    {
        $this->decode(hex2bin('1303').'foo')->shouldBeLike(new PrintableStringType('foo'));
    }

    function it_should_encode_a_teletex_string()
    {
        $this->encode(new TeletexStringType('foo'))->shouldBeLike(hex2bin('1403').'foo');
    }

    function it_should_decode_a_teletex_string()
    {
        $this->decode(hex2bin('1403').'foo')->shouldBeLike(new TeletexStringType('foo'));
    }

    function it_should_encode_a_universal_string()
    {
        $this->encode(new UniversalStringType('foo'))->shouldBeLike(hex2bin('1c03').'foo');
    }

    function it_should_decode_a_universal_string()
    {
        $this->decode(hex2bin('1c03').'foo')->shouldBeLike(new UniversalStringType('foo'));
    }

    function it_should_encode_a_utf8_string()
    {
        $this->encode(new Utf8StringType('foo'))->shouldBeLike(hex2bin('0c03').'foo');
    }

    function it_should_decode_a_utf8_string()
    {
        $this->decode(hex2bin('0c03').'foo')->shouldBeLike(new Utf8StringType('foo'));
    }

    function it_should_encode_a_videotex_string()
    {
        $this->encode(new VideotexStringType('foo'))->shouldBeLike(hex2bin('1503').'foo');
    }

    function it_should_decode_a_videotex_string()
    {
        $this->decode(hex2bin('1503').'foo')->shouldBeLike(new VideotexStringType('foo'));
    }

    function it_should_encode_a_visible_string()
    {
        $this->encode(new VisibleStringType('foo'))->shouldBeLike(hex2bin('1a03').'foo');
    }

    function it_should_decode_a_visible_string()
    {
        $this->decode(hex2bin('1a03').'foo')->shouldBeLike(new VisibleStringType('foo'));
    }

    function it_should_throw_an_encoder_exception_on_decoding_an_invalid_oid()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('0600')]);
    }

    function it_should_throw_an_encoder_exception_on_encoding_an_invalid_oid()
    {
        $this->shouldThrow(EncoderException::class)->during('encode', [new OidType('1')]);
    }

    function it_should_encode_a_relative_oid()
    {
        $this->encode(new RelativeOidType('6.1.4.1.311.21.20'))->shouldBeEqualTo(hex2bin('0d080601040182371514'));
        $this->encode(new RelativeOidType('268435455'))->shouldBeEqualTo(hex2bin('0d04ffffff7f'));
    }

    function it_should_decode_a_relative_oid()
    {
        $this->decode(hex2bin('0d080601040182371514'))->shouldBeLike(new RelativeOidType('6.1.4.1.311.21.20'));
        $this->decode(hex2bin('0d04ffffff7f'))->shouldBeLike(new RelativeOidType('268435455'));
    }

    function it_should_decode_an_unknown_type()
    {
        $incompleteType = new IncompleteType(hex2bin('01'));
        $incompleteType->setTagClass(AbstractType::TAG_CLASS_PRIVATE)->setTagNumber(7);

        $this->decode(hex2bin('c70101'))->shouldBeLike($incompleteType);
    }

    function it_should_throw_an_error_when_decoding_incorrect_length()
    {
        $this->shouldThrow(new EncoderException('The expected byte length was 4, but received 3.'))->duringDecode(hex2bin('010201'));
    }

    function it_should_throw_an_error_if_indefinite_length_encoding_is_used()
    {
        $this->shouldThrow(new EncoderException('Indefinite length encoding is not supported.'))->duringDecode(hex2bin('0180010000'));
    }

    function it_should_save_trailing_data()
    {
        $type = (new BooleanType(true))->setTrailingData(0x00);
        $this->decode(hex2bin('0101FF00'))->shouldBeLike($type);
    }

    function it_should_throw_a_partial_PDU_exception_with_only_a_byte_of_data()
    {
        $this->shouldThrow(PartialPduException::class)->duringDecode(hex2bin('30'));
    }

    function it_should_throw_a_partial_PDU_exception_without_enough_data_to_decode_length()
    {
        $this->shouldThrow(new PartialPduException('Not enough data to decode the length.'))->duringDecode(hex2bin('048301ff'));
        $this->shouldNotThrow(PartialPduException::class)->during('decode', [hex2bin('30840000003702010264840000002e0426434e3d436861642c434e3d55736572732c44433d6c646170746f6f6c732c44433d6c6f63616c308400000000')]);
    }

    function it_should_detect_a_context_specific_tag_type_correctly()
    {
        $this->decode(hex2bin('800001'))->getTagClass()->shouldBeEqualTo(AbstractType::TAG_CLASS_CONTEXT_SPECIFIC);
    }

    function it_should_detect_an_application_tag_correctly()
    {
        $this->decode(hex2bin('6000'))->getTagClass()->shouldBeEqualTo(AbstractType::TAG_CLASS_APPLICATION);
    }

    function it_should_detect_a_private_tag_correctly()
    {
        $this->decode(hex2bin('c00001'))->getTagClass()->shouldBeEqualTo(AbstractType::TAG_CLASS_PRIVATE);
    }

    function it_should_detect_a_universal_tag_correctly()
    {
        $this->decode(hex2bin('010101'))->getTagClass()->shouldBeEqualTo(AbstractType::TAG_CLASS_UNIVERSAL);
    }

    function it_should_complete_an_incomplete_type()
    {
        $this->complete((new IncompleteType(hex2bin('FF')))->setTagNumber(5), AbstractType::TAG_TYPE_BOOLEAN)
            ->shouldBeLike((new BooleanType(true))->setTagNumber(5));
    }

    function it_should_decode_a_high_tag_number_properly()
    {
        $this->decode(hex2bin('5f1f0101'))->shouldBeLike(
            (new IncompleteType(hex2bin('01')))
                ->setTagNumber(31)
                ->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
        );
        $this->decode(hex2bin('5f81000101'))->shouldBeLike(
            (new IncompleteType(hex2bin('01')))
                ->setTagNumber(128)
                ->setTagClass(AbstractType::TAG_CLASS_APPLICATION)
        );
    }

    function it_should_throw_a_partial_pdu_exception_on_a_root_type_with_no_high_tag_ending()
    {
        $this->shouldThrow(PartialPduException::class)->during('decode', [hex2bin('5f8080')]);
    }

    function it_should_throw_an_encoder_exception_on_a_non_root_type_with_no_high_tag_ending()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('30035f8080')]);
    }

    function it_should_encode_a_high_tag_number_properly()
    {
        $this->encode((new BooleanType(true))->setTagNumber(128))->shouldBeEqualTo(hex2bin('1f810001ff'));
    }

    function it_should_throw_an_exception_on_zero_length_boolean()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('0100')]);
    }

    function it_should_throw_an_exception_on_zero_length_integer()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('0200')]);
    }

    function it_should_throw_an_exception_on_zero_length_enumerated()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('0a00')]);
    }

    function it_should_throw_an_exception_on_zero_length_oid()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('0600')]);
    }

    function it_should_throw_an_exception_on_zero_length_relative_oid()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('0d00')]);
    }

    function it_should_throw_an_exception_on_zero_length_generalized_time()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('1800')]);
    }

    function it_should_throw_an_exception_on_zero_length_utc_time()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('1700')]);
    }

    function it_should_throw_an_exception_if_a_bool_with_more_than_one_byte_of_length_is_encountered()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('0102ffff')]);
    }

    function it_should_throw_an_exception_if_a_null_with_one_or_more_bytes_of_length_is_encountered()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('050101')]);
    }

    function it_should_throw_an_exception_on_a_constructed_boolean()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('210101')]);
    }

    function it_should_throw_an_exception_on_a_constructed_integer()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('220101')]);
    }

    function it_should_throw_an_exception_on_a_constructed_enumerated()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('2a0101')]);
    }

    function it_should_throw_an_exception_on_a_constructed_oid()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('38022a7f')]);
    }

    function it_should_throw_an_exception_on_a_constructed_relative_oid()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('2d022a7f')]);
    }

    function it_should_throw_an_exception_on_a_constructed_real()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('290101')]);
    }

    function it_should_throw_an_exception_on_a_constructed_null()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('2500')]);
    }

    function it_should_throw_an_exception_on_a_primitive_sequence()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('10030101ff')]);
    }

    function it_should_throw_an_exception_on_a_primitive_set()
    {
        $this->shouldThrow(EncoderException::class)->during('decode', [hex2bin('11030101ff')]);
    }
}
