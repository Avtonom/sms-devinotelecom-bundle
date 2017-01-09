<?php

namespace Avtonom\Sms\DevinoTelecomBundle\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolverInterface;
use Symfony\Component\Validator\Constraints\Choice;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\Type;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;

class DevinoTelecomMessageType extends AbstractType
{
    /**
     * @var array
     */
    protected $originator;

    public function __construct(array $originator = array())
    {
        $this->originator = $originator;
    }

    /**
     * @param FormBuilderInterface $builder
     * @param array $options
     */
    public function buildForm(FormBuilderInterface $builder, array $options)
    {
        $builder
            ->add('phone', IntegerType::class, [//recipient
                'required' => true,
                'label' => 'Номер мобильного телефона',
                'constraints' => array(
                    new NotBlank(),
                    new Type(['type' => 'integer']),
                    new Length(array('min' => 11, 'max' => 11)),
                ),
                'mapped' => false,
            ])
            ->add('text', TextType::class, [
                'required' => true,
                'label' => 'Текст сообщения',
                'constraints' => array(
                    new NotBlank(),
                    new Length(array('min' => 1, 'max' => 255)),
                ),
                'mapped' => false,
            ])
        ;

        if(empty($this->originator)){
            $builder->add('from', TextType::class, [//originator
                'required' => true,
                'label' => 'Имя отправителя',
//                'description' => 'До 11 латинских символов или до  15  цифровых',
                'constraints' => array(
                    new NotBlank(),
                    new Length(array('min' => 1, 'max' => 15)),
                ),
                'mapped' => false,
            ]);
        } else {
            $builder->add('from', ChoiceType::class, [//originator
                'required' => true,
                'label' => 'Имя отправителя',
                'choices' => $this->originator,
                'constraints' => array(
                    new NotBlank(),
                    new Choice(['choices' => $this->originator]),
                ),
                'mapped' => false,
            ]);
        }
    }

    /**
     * @param OptionsResolverInterface $resolver
     */
    public function setDefaultOptions(OptionsResolverInterface $resolver)
    {
        $resolver->setDefaults(array(
            'csrf_protection' => false,
        ));
    }
}
