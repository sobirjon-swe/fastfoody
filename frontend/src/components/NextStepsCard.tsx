import { CircleDashed } from 'lucide-react'

import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card'

/**
 * Every role screen is a shell until its roadmap stage is built; this card
 * states what will land there instead of showing invented data.
 */
export function NextStepsCard({ title, steps }: { title: string; steps: string[] }) {
  return (
    <Card>
      <CardHeader>
        <CardTitle>{title}</CardTitle>
        <CardDescription>Keyingi bosqichlarda shu sahifaga qoʻshiladi.</CardDescription>
      </CardHeader>
      <CardContent>
        <ul className="grid gap-2">
          {steps.map((step) => (
            <li key={step} className="text-muted-foreground flex items-start gap-2 text-sm">
              <CircleDashed className="mt-0.5 size-4 shrink-0" />
              {step}
            </li>
          ))}
        </ul>
      </CardContent>
    </Card>
  )
}
