<?php

namespace AV\ActivityPubBundle\Controller;

use AV\ActivityPubBundle\Entity\Actor;
use AV\ActivityPubBundle\Entity\BaseObject;
use AV\ActivityPubBundle\Serializer\ObjectSerializer;
use AV\ActivityPubBundle\Serializer\Serializable;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

class ObjectController extends BaseController
{
    /**
     * @Route("/object/{id}", name="av_activitypub_object_get", methods={"GET"})
     */
    public function getObject(string $id)
    {
        $em = $this->getDoctrine()->getManager();
        /** @var ObjectSerializer $objectSerializer */
        $objectSerializer = $this->container->get('activity_pub.serializer.object.medium');

        /** @var Actor $actor */
        $object = $em->getRepository(BaseObject::class)->findOneBy(['id' => $id]);
        if( !$object ) throw new NotFoundHttpException();

        return $this->json(new Serializable($object, $objectSerializer));
    }
}