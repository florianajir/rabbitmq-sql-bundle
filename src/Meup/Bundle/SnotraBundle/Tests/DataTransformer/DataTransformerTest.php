<?php
namespace Meup\Bundle\SnotraBundle\Tests\DataTransformer;

use Meup\Bundle\SnotraBundle\DataMapper\DataMapper;
use Meup\Bundle\SnotraBundle\DataTransformer\DataTransformer;
use Meup\Bundle\SnotraBundle\DataValidator\DataValidator;
use Meup\Bundle\SnotraBundle\Model\Entity;
use PHPUnit_Framework_TestCase;

/**
 * Class DataTransformerTest
 *
 * @author florianajir <florian@1001pharmacies.com>
 */
class DataTransformerTest extends PHPUnit_Framework_TestCase
{

    /**
     * The simple mapping fixture
     *
     * @var array
     */
    protected $mapping;

    /**
     * The relationnal mapping fixture
     *
     * @var array
     */
    protected $relationnalMapping;

    /**
     * The message data
     *
     * @var array
     */
    protected $data;

    /**
     *
     */
    public function testPrepare()
    {
        $mapper = new DataMapper($this->mapping);
        $dataTransformer = new DataTransformer($mapper);
        $data = array(
            'identifier' => '1',
            'label'      => 'label_de_test',
            'amount'     => '10.01',
            'birthday'   => '1989-11-10',
            'subscribe'  => '2015-01-02T09:00:00+0200'
        );
        $expected = array(
            'user' => array(
                'id'          => '1',
                'name'        => 'label_de_test',
                'amount'      => '10.01',
                'birthdate'   => '1989-11-10',
                'created_at'  => '2015-01-02T09:00:00+0200',
                '_identifier' => 'id',
                '_table'      => 'users'
            )
        );
        $this->assertEquals($expected, $dataTransformer->prepare('user', $data));
    }

    /**
     *
     */
    public function testPrepareMissingNotNullableProperty()
    {
        $mapper = new DataMapper($this->mapping);
        $validator = new DataValidator();
        $dataTransformer = new DataTransformer($mapper, $validator);
        $data = array(
            'label'     => 'label_de_test',
            'amount'    => '10.01',
            'birthday'  => '1989-11-10',
            'subscribe' => '2015-01-02T09:00:00+0200'
        );
        $this->setExpectedException('InvalidArgumentException', 'user.identifier is not nullable.');
        $dataTransformer->prepare('user', $data);
    }

    /**
     *
     */
    public function testPrepareWrongType()
    {
        $mapper = new DataMapper($this->mapping);
        $validator = new DataValidator();
        $dataTransformer = new DataTransformer($mapper, $validator);

        $data = array(
            'identifier' => '1',
            'amount'     => '10,01'
        );
        $this->setExpectedException('InvalidArgumentException', 'user.amount type not valid.');
        $dataTransformer->prepare('user', $data);
        $data = array(
            'identifier' => '1',
            'birthday'   => '11/10/1989'
        );
        $this->setExpectedException('InvalidArgumentException', 'user.birthday type not valid.');
        $dataTransformer->prepare('user', $data);
        $data = array(
            'identifier' => '1',
            'subscribe'  => '2015-01-02'
        );
        $this->setExpectedException('InvalidArgumentException', 'user.subscribe type not valid.');
        $dataTransformer->prepare('user', $data);
    }

    /**
     *
     */
    public function testPrepareRelationnal()
    {
        $mapper = new DataMapper($this->relationnalMapping);
        $dataTransformer = new DataTransformer($mapper);
        $data = array(
            'identifier' => '1',
            'label'      => 'label_de_test',
            'Address'    => array(
                'identifier'  => '2',
                'postal_code' => '34000',
                'city'        => 'Montpellier'
            )
        );
        $expected = array(
            'User' => array(
                'sku'         => '1',
                '_identifier' => 'sku',
                '_table'      => 'users',
                '_related'    => array(
                    'manyToOne' => array(
                        'Address' => array(
                            '_relation' => array(
                                'targetEntity' => 'Address',
                                'joinColumn'   => array(
                                    'name'                 => 'address_id',
                                    'referencedColumnName' => 'id'
                                ),
                                'table'        => 'address'
                            ),
                            '_data'     => array(
                                'Address' => array(
                                    '_identifier' => 'sku',
                                    '_table'      => 'address',
                                    'sku'         => '2',
                                    'postal_code' => '34000',
                                    'city'        => 'Montpellier'
                                )
                            )
                        )
                    )
                )
            )
        );
        $result = $dataTransformer->prepare('User', $data);
        $this->assertEquals($expected, $result);
    }

    /**
     *
     */
    public function testPrepareWithDiscriminator()
    {
        $mapper = new DataMapper(array(
            'supplier' => array(
                'discriminator' => 'dtype',
                'fields'        => array(
                    'sku' => array(
                        'column' => 'sku',
                        'type'   => 'string',
                    )
                )
            )
        ));
        $dataTransformer = new DataTransformer($mapper);
        $data = array(
            'sku'   => '1234567',
            'dtype' => 'brand'
        );
        $expected = array(
            'supplier' => array(
                'sku'  => '1234567',
                '_table' => 'brand'
            )
        );
        $result = $dataTransformer->prepare('supplier', $data);
        $this->assertEquals($expected, $result);

        $entity = new Entity($result['supplier']);
        $this->assertEquals('brand', $entity->getTable());
    }

    /**
     *
     */
    public function testValidateLengthExceed()
    {
        $mapper = new DataMapper(array(
            'user' => array(
                'table' => 'user',
                'fields' => array(
                    'sku' => array(
                        'column' => 'sku',
                        'type'   => 'string',
                        'length'   => '7'
                    )
                )
            )
        ));
        $dataTransformer = new DataTransformer($mapper, new DataValidator());
        $data = array(
            'sku'   => '12345678'
        );
        $this->setExpectedException('InvalidArgumentException');
        $dataTransformer->prepare('user', $data);
    }

    /**
     * Sets up the fixture
     */
    protected function setUp()
    {
        $this->mapping = array(
            'user' => array(
                'table'      => 'users',
                'identifier' => 'id',
                'fields'     => array(
                    'identifier' => array(
                        'column'   => 'id',
                        'type'     => 'int',
                        'length'   => '8',
                        'nullable' => 'false'
                    ),
                    'label'      => array(
                        'column' => 'name',
                        'type'   => 'string',
                    ),
                    'amount'     => array(
                        'column' => 'amount',
                        'type'   => 'decimal',
                    ),
                    'birthday'   => array(
                        'column' => 'birthdate',
                        'type'   => 'date',
                    ),
                    'subscribe'  => array(
                        'column' => 'created_at',
                        'type'   => 'datetime',
                    ),
                    'missing'    => array(
                        'column'   => 'missing',
                        'type'     => 'string',
                        'nullable' => 'true'
                    )
                )
            )
        );

        $this->relationnalMapping = array(
            'User'    => array(
                'table'      => 'users',
                'identifier' => 'sku',
                'fields'     => array(
                    'identifier' => array(
                        'column'   => 'sku',
                        'type'     => 'int',
                        'length'   => '8',
                        'nullable' => 'false'
                    )
                ),
                'manyToOne'  =>
                    array(
                        'Address' =>
                            array(
                                'joinColumn'   => array(
                                    'referencedColumnName' => 'id',
                                    'name'                 => 'address_id',
                                ),
                                'targetEntity' => 'Address',
                            ),
                    )
            ),
            'Address' => array(
                'table'      => 'address',
                'identifier' => 'sku',
                'fields'     => array(
                    'identifier'  => array(
                        'column' => 'sku',
                        'type'   => 'string'
                    ),
                    'postal_code' => array(
                        'column' => 'postal_code',
                        'type'   => 'string',
                        'length' => '5'
                    ),
                    'city'        => array(
                        'column' => 'city',
                        'type'   => 'string',
                    ),
                )
            )
        );
    }
}
