import { Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts'

export type SparklinePoint = { idx: number; total: number }

export default function Sparkline({ data, color = '#0ea5e9' }: { data: SparklinePoint[]; color?: string }) {
  return (
    <ResponsiveContainer width="100%" height="100%">
      <LineChart data={data} margin={{ top: 6, right: 6, bottom: 0, left: 0 }}>
        <XAxis dataKey="idx" hide />
        <YAxis hide />
        <Tooltip formatter={(v) => String(v)} labelFormatter={() => ''} />
        <Line type="monotone" dataKey="total" stroke={color} strokeWidth={2} dot={false} />
      </LineChart>
    </ResponsiveContainer>
  )
}
